<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PurchaseHistoryBackfillService
{
    public function legacyPurchaseLayerRows(?string $tenantId = null): Collection
    {
        return DB::table('inventory_layers')
            ->where('source_type', 'purchase')
            ->whereNull('source_item_id')
            ->when(($tenantId ?? '') !== '', function ($query) use ($tenantId): void {
                $query->where('tenant_id', $tenantId);
            })
            ->orderBy('received_at')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get([
                'id',
                'tenant_id',
                'product_id',
                'product_variant_id',
                'location_id',
                'qty_original',
                'qty_remaining',
                'cost_per_unit',
                'source_id',
                'received_at',
                'created_at',
            ]);
    }

    public function legacyPurchaseReturnRows(?string $tenantId = null): Collection
    {
        return DB::table('purchase_return_items')
            ->join('purchase_returns', function ($join) {
                $join->on('purchase_returns.id', '=', 'purchase_return_items.purchase_return_id')
                    ->on('purchase_returns.tenant_id', '=', 'purchase_return_items.tenant_id');
            })
            ->whereNull('purchase_return_items.purchase_item_id')
            ->when(($tenantId ?? '') !== '', function ($query) use ($tenantId): void {
                $query->where('purchase_return_items.tenant_id', $tenantId);
            })
            ->orderBy('purchase_returns.created_at')
            ->orderBy('purchase_return_items.id')
            ->get([
                'purchase_return_items.id as purchase_return_item_id',
                'purchase_return_items.tenant_id',
                'purchase_return_items.purchase_return_id',
                'purchase_returns.purchase_id',
                'purchase_return_items.product_id',
                'purchase_return_items.product_variant_id',
                'purchase_return_items.qty',
                'purchase_return_items.price',
                'purchase_returns.created_at as purchase_return_created_at',
            ]);
    }

    public function audit(?string $tenantId = null): array
    {
        $layerRows = $this->legacyPurchaseLayerRows($tenantId)
            ->map(fn (object $row) => array_merge((array) $row, $this->classifyLayer($row)))
            ->values();

        $returnRows = $this->legacyPurchaseReturnRows($tenantId)
            ->map(fn (object $row) => array_merge((array) $row, $this->classifyPurchaseReturnItem($row)))
            ->values();

        return [
            'purchase_layers' => [
                'rows' => $layerRows,
                'summary' => $this->classificationSummary($layerRows, 'status'),
            ],
            'purchase_return_items' => [
                'rows' => $returnRows,
                'summary' => $this->classificationSummary($returnRows, 'status'),
            ],
        ];
    }

    public function backfillPurchaseLayerSourceItems(?string $tenantId = null, bool $persist = false, int $limit = 1000): array
    {
        $rows = $this->legacyPurchaseLayerRows($tenantId)
            ->map(fn (object $row) => array_merge((array) $row, $this->classifyLayer($row)))
            ->values();

        $resolvable = $rows
            ->filter(fn (array $row) => in_array($row['status'] ?? null, ['resolved_exact_ledger', 'resolved_single_purchase_item'], true))
            ->take($limit)
            ->values();

        $updated = 0;
        $updatedRows = [];

        if ($persist) {
            DB::transaction(function () use ($resolvable, &$updated, &$updatedRows): void {
                foreach ($resolvable as $row) {
                    $count = DB::table('inventory_layers')
                        ->where('tenant_id', $row['tenant_id'])
                        ->where('id', $row['id'])
                        ->whereNull('source_item_id')
                        ->update([
                            'source_item_id' => $row['resolved_source_item_id'],
                        ]);

                    if ($count !== 1) {
                        continue;
                    }

                    $updated++;
                    $updatedRows[] = [
                        'layer_id' => $row['id'],
                        'tenant_id' => $row['tenant_id'],
                        'purchase_id' => $row['source_id'],
                        'source_item_id' => $row['resolved_source_item_id'],
                        'resolution' => $row['status'],
                    ];

                    app(ValuationAuditService::class)->log($row['tenant_id'], 'valuation_purchase_layer_backfill_resolved', [
                        'layer_id' => $row['id'],
                        'purchase_id' => $row['source_id'],
                        'source_item_id' => $row['resolved_source_item_id'],
                        'resolution' => $row['status'],
                    ]);
                }
            });

            $rows
                ->filter(fn (array $row) => in_array($row['status'] ?? null, ['legacy_ambiguous', 'orphan_no_match'], true))
                ->each(function (array $row): void {
                    $type = ($row['status'] ?? null) === 'legacy_ambiguous'
                        ? 'valuation_purchase_layer_backfill_legacy_ambiguous'
                        : 'valuation_purchase_layer_backfill_orphan_no_match';

                    $payload = [
                        'layer_id' => $row['id'],
                        'purchase_id' => $row['source_id'],
                        'product_id' => $row['product_id'],
                        'product_variant_id' => $row['product_variant_id'],
                        'location_id' => $row['location_id'],
                        'qty_original' => (float) $row['qty_original'],
                        'cost_per_unit' => (float) $row['cost_per_unit'],
                        'status' => $row['status'],
                        'candidate_count' => $row['candidate_count'] ?? 0,
                        'candidate_source_item_ids' => $row['candidate_source_item_ids'] ?? [],
                    ];

                    app(ValuationAuditService::class)->log($row['tenant_id'], $type, $payload);
                    app(AnomalyService::class)->raise(
                        tenantId: $row['tenant_id'],
                        severity: 'high',
                        type: $type,
                        payload: $payload,
                        referenceId: $row['id'],
                    );
                });
        }

        return [
            'summary' => $this->classificationSummary($rows, 'status'),
            'updated' => $updated,
            'updated_rows' => $updatedRows,
            'rows' => $rows,
        ];
    }

    public function resolveLegacyPurchaseReturnItem(string $tenantId, string $purchaseReturnItemId): array
    {
        $row = DB::table('purchase_return_items')
            ->join('purchase_returns', function ($join) {
                $join->on('purchase_returns.id', '=', 'purchase_return_items.purchase_return_id')
                    ->on('purchase_returns.tenant_id', '=', 'purchase_return_items.tenant_id');
            })
            ->where('purchase_return_items.tenant_id', $tenantId)
            ->where('purchase_return_items.id', $purchaseReturnItemId)
            ->first([
                'purchase_return_items.id as purchase_return_item_id',
                'purchase_return_items.purchase_return_id',
                'purchase_return_items.purchase_item_id',
                'purchase_return_items.product_id',
                'purchase_return_items.product_variant_id',
                'purchase_return_items.price',
                'purchase_returns.purchase_id',
            ]);

        if (! $row) {
            return [
                'status' => 'not_found',
                'purchase_item_id' => null,
            ];
        }

        if ($row->purchase_item_id) {
            return [
                'status' => 'resolved_existing',
                'purchase_item_id' => (string) $row->purchase_item_id,
                'purchase_id' => (string) $row->purchase_id,
                'product_variant_id' => $row->product_variant_id ? (string) $row->product_variant_id : null,
            ];
        }

        return $this->classifyPurchaseReturnItem($row);
    }

    private function classifyLayer(object $row): array
    {
        $ledgerCandidates = $this->ledgerCandidateReferenceItemIds($row);

        if ($ledgerCandidates->count() === 1) {
            return [
                'status' => 'resolved_exact_ledger',
                'resolved_source_item_id' => $ledgerCandidates->first(),
                'candidate_count' => 1,
                'candidate_source_item_ids' => $ledgerCandidates->values()->all(),
            ];
        }

        if ($ledgerCandidates->count() > 1) {
            return [
                'status' => 'legacy_ambiguous',
                'resolved_source_item_id' => null,
                'candidate_count' => $ledgerCandidates->count(),
                'candidate_source_item_ids' => $ledgerCandidates->values()->all(),
            ];
        }

        $purchaseItemCandidates = $this->purchaseItemCandidates($row);

        if ($purchaseItemCandidates->count() === 1) {
            return [
                'status' => 'resolved_single_purchase_item',
                'resolved_source_item_id' => (string) $purchaseItemCandidates->first()->id,
                'candidate_count' => 1,
                'candidate_source_item_ids' => [(string) $purchaseItemCandidates->first()->id],
            ];
        }

        if ($purchaseItemCandidates->count() > 1) {
            return [
                'status' => 'legacy_ambiguous',
                'resolved_source_item_id' => null,
                'candidate_count' => $purchaseItemCandidates->count(),
                'candidate_source_item_ids' => $purchaseItemCandidates->pluck('id')->map(fn ($id) => (string) $id)->values()->all(),
            ];
        }

        return [
            'status' => 'orphan_no_match',
            'resolved_source_item_id' => null,
            'candidate_count' => 0,
            'candidate_source_item_ids' => [],
        ];
    }

    private function classifyPurchaseReturnItem(object $row): array
    {
        $query = DB::table('purchase_items')
            ->where('tenant_id', $row->tenant_id)
            ->where('purchase_id', $row->purchase_id)
            ->where('product_id', $row->product_id)
            ->where('price', $row->price);

        $query = $row->product_variant_id !== null
            ? $query->where('product_variant_id', $row->product_variant_id)
            : $query->whereNull('product_variant_id');

        $candidates = $query
            ->orderBy('id')
            ->get(['id', 'product_variant_id']);

        if ($candidates->count() === 1) {
            $candidate = $candidates->first();

            return [
                'status' => 'resolved_single_purchase_item',
                'purchase_item_id' => (string) $candidate->id,
                'purchase_id' => (string) $row->purchase_id,
                'product_variant_id' => $candidate->product_variant_id ? (string) $candidate->product_variant_id : null,
                'candidate_count' => 1,
                'candidate_purchase_item_ids' => [(string) $candidate->id],
            ];
        }

        if ($candidates->count() > 1) {
            return [
                'status' => 'legacy_ambiguous',
                'purchase_item_id' => null,
                'purchase_id' => (string) $row->purchase_id,
                'product_variant_id' => $row->product_variant_id ? (string) $row->product_variant_id : null,
                'candidate_count' => $candidates->count(),
                'candidate_purchase_item_ids' => $candidates->pluck('id')->map(fn ($id) => (string) $id)->values()->all(),
            ];
        }

        return [
            'status' => 'orphan_no_match',
            'purchase_item_id' => null,
            'purchase_id' => (string) $row->purchase_id,
            'product_variant_id' => $row->product_variant_id ? (string) $row->product_variant_id : null,
            'candidate_count' => 0,
            'candidate_purchase_item_ids' => [],
        ];
    }

    private function ledgerCandidateReferenceItemIds(object $row): Collection
    {
        $query = DB::table('stock_ledger')
            ->where('tenant_id', $row->tenant_id)
            ->where('reference_type', 'purchase')
            ->where('reference_id', $row->source_id)
            ->where('product_id', $row->product_id)
            ->where('location_id', $row->location_id)
            ->whereNotNull('reference_item_id')
            ->where('qty_in', $row->qty_original)
            ->where('created_at', $row->received_at);

        $query = $row->product_variant_id !== null
            ? $query->where('product_variant_id', $row->product_variant_id)
            : $query->whereNull('product_variant_id');

        return $query
            ->pluck('reference_item_id')
            ->filter(fn ($value) => is_string($value) && $value !== '')
            ->map(fn ($value) => (string) $value)
            ->unique()
            ->values();
    }

    private function purchaseItemCandidates(object $row): Collection
    {
        $query = DB::table('purchase_items')
            ->where('tenant_id', $row->tenant_id)
            ->where('purchase_id', $row->source_id)
            ->where('product_id', $row->product_id)
            ->where('price', $row->cost_per_unit);

        $query = $row->product_variant_id !== null
            ? $query->where('product_variant_id', $row->product_variant_id)
            : $query->whereNull('product_variant_id');

        return $query
            ->orderBy('id')
            ->get(['id']);
    }

    private function classificationSummary(Collection $rows, string $key): array
    {
        return [
            'total' => $rows->count(),
            'resolved_exact_ledger' => $rows->where($key, 'resolved_exact_ledger')->count(),
            'resolved_single_purchase_item' => $rows->where($key, 'resolved_single_purchase_item')->count(),
            'resolved_existing' => $rows->where($key, 'resolved_existing')->count(),
            'legacy_ambiguous' => $rows->where($key, 'legacy_ambiguous')->count(),
            'orphan_no_match' => $rows->where($key, 'orphan_no_match')->count(),
        ];
    }
}