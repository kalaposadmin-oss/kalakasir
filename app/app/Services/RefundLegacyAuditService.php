<?php

namespace App\Services;

use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RefundLegacyAuditService
{
    private const NULL_VARIANT_SENTINEL = '__NULL_VARIANT__';

    public function rows(?string $tenantId = null, array $filters = []): Collection
    {
        $saleItemMatches = DB::table('sale_items')
            ->select('tenant_id', 'sale_id', 'product_id')
            ->selectRaw("COALESCE(CAST(product_variant_id AS CHAR), ?) as product_variant_match_key", [self::NULL_VARIANT_SENTINEL])
            ->selectRaw('COUNT(*) as sale_item_match_count')
            ->groupBy('tenant_id', 'sale_id', 'product_id', 'product_variant_id');

        $query = DB::table('refund_items')
            ->join('refunds', function ($join) {
                $join->on('refunds.id', '=', 'refund_items.refund_id')
                    ->on('refunds.tenant_id', '=', 'refund_items.tenant_id');
            })
            ->leftJoin('branches', function ($join) {
                $join->on('branches.id', '=', 'refunds.branch_id')
                    ->on('branches.tenant_id', '=', 'refunds.tenant_id');
            })
            ->leftJoinSub($saleItemMatches, 'sale_item_matches', function ($join) {
                $join->on('sale_item_matches.tenant_id', '=', 'refund_items.tenant_id')
                    ->on('sale_item_matches.sale_id', '=', 'refunds.sale_id')
                    ->on('sale_item_matches.product_id', '=', 'refund_items.product_id')
                    ->whereRaw(
                        "sale_item_matches.product_variant_match_key = COALESCE(CAST(refund_items.product_variant_id AS CHAR), ?)",
                        [self::NULL_VARIANT_SENTINEL]
                    );
            })
            ->whereNull('refund_items.sale_item_id');

        if (($tenantId ?? '') !== '') {
            $query->where('refund_items.tenant_id', $tenantId);
        }

        if ($branchId = $filters['branch_id'] ?? null) {
            $query->where('refunds.branch_id', $branchId);
        }

        if ($dateFrom = $filters['date_from'] ?? null) {
            $query->whereDate('refunds.created_at', '>=', $dateFrom);
        }

        if ($dateTo = $filters['date_to'] ?? null) {
            $query->whereDate('refunds.created_at', '<=', $dateTo);
        }

        return $query
            ->select([
                'refund_items.id as refund_item_id',
                'refund_items.tenant_id',
                'refund_items.refund_id',
                'refunds.sale_id',
                'refunds.branch_id',
                'branches.name as branch_name',
                'refund_items.product_id',
                'refund_items.product_variant_id',
                'refund_items.location_id',
                'refund_items.qty',
                'refund_items.amount',
                'refunds.created_at as refund_created_at',
            ])
            ->selectRaw('COALESCE(sale_item_matches.sale_item_match_count, 0) as sale_item_match_count')
            ->orderBy('refunds.created_at')
            ->orderBy('refund_items.id')
            ->get();
    }

    public function tenantSummary(Collection $rows): Collection
    {
        return $rows
            ->groupBy('tenant_id')
            ->map(function ($tenantRows, $tenantId) {
                return [
                    'tenant_id' => $tenantId,
                    'legacy_missing_sale_item_id' => $tenantRows->count(),
                    'resolvable_single_match' => $tenantRows->filter(fn ($row) => $this->classify($row) === 'resolvable_single_match')->count(),
                    'ambiguous_multi_match' => $tenantRows->filter(fn ($row) => $this->classify($row) === 'ambiguous_multi_match')->count(),
                    'orphan_no_match' => $tenantRows->filter(fn ($row) => $this->classify($row) === 'orphan_no_match')->count(),
                ];
            })
            ->sortByDesc('legacy_missing_sale_item_id')
            ->values();
    }

    public function summary(Collection $rows): array
    {
        return [
            'legacy_missing_sale_item_id' => $rows->count(),
            'resolvable_single_match' => $rows->filter(fn ($row) => $this->classify($row) === 'resolvable_single_match')->count(),
            'ambiguous_multi_match' => $rows->filter(fn ($row) => $this->classify($row) === 'ambiguous_multi_match')->count(),
            'orphan_no_match' => $rows->filter(fn ($row) => $this->classify($row) === 'orphan_no_match')->count(),
        ];
    }

    public function classify(object $row): string
    {
        return match (true) {
            (int) $row->sale_item_match_count === 1 => 'resolvable_single_match',
            (int) $row->sale_item_match_count > 1 => 'ambiguous_multi_match',
            default => 'orphan_no_match',
        };
    }

    /**
     * @return array{entries: array<int, array<string, mixed>>, total: int}
     */
    public function history(string $tenantId, array $filters = [], int $page = 1, int $perPage = 10): array
    {
        $query = DB::table('audit_logs as a')
            ->leftJoin('users as u', function ($join) {
                $join->on('u.id', '=', 'a.actor_id')
                    ->on('u.tenant_id', '=', 'a.tenant_id');
            })
            ->where('a.tenant_id', $tenantId)
            ->where('a.event_type', 'refund_legacy_remediated');

        if ($actorId = $filters['actor_id'] ?? null) {
            $query->where('a.actor_id', $actorId);
        }

        if ($dateFrom = $filters['date_from'] ?? null) {
            $query->whereDate('a.created_at', '>=', $dateFrom);
        }

        if ($dateTo = $filters['date_to'] ?? null) {
            $query->whereDate('a.created_at', '<=', $dateTo);
        }

        $total = (int) $query->count();

        $rows = $query
            ->orderByDesc('a.created_at')
            ->offset(max($page - 1, 0) * $perPage)
            ->limit($perPage)
            ->get([
                'a.id',
                'a.actor_id',
                'u.name as actor_name',
                'a.reference_type',
                'a.reference_id',
                'a.payload_json',
                'a.created_at',
            ]);

        return [
            'entries' => $rows->map(function ($row) {
                $payload = is_array($row->payload_json)
                    ? $row->payload_json
                    : json_decode((string) $row->payload_json, true);

                return [
                    'id' => $row->id,
                    'actor_id' => $row->actor_id,
                    'actor_name' => $row->actor_name,
                    'reference_type' => $row->reference_type,
                    'reference_id' => $row->reference_id,
                    'payload_json' => $payload ?: [],
                    'created_at' => $row->created_at
                        ? CarbonImmutable::parse($row->created_at, 'UTC')->toIso8601String()
                        : null,
                ];
            })->all(),
            'total' => $total,
        ];
    }

    public function exportHistoryCsv(string $tenantId, array $filters = []): StreamedResponse
    {
        $history = $this->history($tenantId, $filters, 1, 1000);
        $filename = 'refund-legacy-remediation-history-'.$tenantId.'.csv';

        return response()->streamDownload(function () use ($history): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, [
                'id',
                'created_at',
                'actor_id',
                'actor_name',
                'reference_type',
                'reference_id',
                'refund_id',
                'sale_id',
                'branch_id',
                'product_id',
                'qty',
                'amount',
                'updated',
                'skipped',
                'skipped_candidates',
                'filter_classification',
                'filter_branch_id',
                'filter_date_from',
                'filter_date_to',
            ]);

            foreach ($history['entries'] as $entry) {
                $payload = $entry['payload_json'] ?? [];
                $filters = $payload['filters'] ?? [];

                fputcsv($handle, [
                    $entry['id'],
                    $entry['created_at'],
                    $entry['actor_id'],
                    $entry['actor_name'],
                    $entry['reference_type'],
                    $entry['reference_id'],
                    $payload['refund_id'] ?? null,
                    $payload['sale_id'] ?? null,
                    $payload['branch_id'] ?? null,
                    $payload['product_id'] ?? null,
                    $payload['qty'] ?? null,
                    $payload['amount'] ?? null,
                    $payload['updated'] ?? null,
                    $payload['skipped'] ?? null,
                    $payload['skipped_candidates'] ?? null,
                    $filters['classification'] ?? null,
                    $filters['branch_id'] ?? null,
                    $filters['date_from'] ?? null,
                    $filters['date_to'] ?? null,
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function remediate(string $tenantId, array $filters = [], array $refundItemIds = [], int $limit = 1000, bool $persist = false): array
    {
        $allRows = $this->rows($tenantId, $filters);
        $requestedIds = collect($refundItemIds)
            ->filter(fn ($value) => is_string($value) && $value !== '')
            ->values();

        $candidateRows = $allRows
            ->when($requestedIds->isNotEmpty(), fn (Collection $rows) => $rows->whereIn('refund_item_id', $requestedIds->all())->values())
            ->values();

        $resolvableRows = $candidateRows
            ->filter(fn ($row) => $this->classify($row) === 'resolvable_single_match')
            ->take($limit)
            ->values();

        $skippedCandidates = max($candidateRows->count() - $resolvableRows->count(), 0);
        $preview = $resolvableRows->take(25)->map(fn ($row) => [
            'refund_item_id' => $row->refund_item_id,
            'refund_id' => $row->refund_id,
            'sale_id' => $row->sale_id,
            'branch_id' => $row->branch_id,
            'branch_name' => $row->branch_name,
            'product_id' => $row->product_id,
            'qty' => Decimal::float(Decimal::qty($row->qty), Decimal::QTY_SCALE),
            'amount' => Decimal::float(Decimal::money($row->amount ?? 0), Decimal::MONEY_SCALE),
            'classification' => $this->classify($row),
        ])->values()->all();

        if (! $persist) {
            return [
                'requested_count' => $requestedIds->count(),
                'matched_candidates' => $candidateRows->count(),
                'matched_resolvable' => $resolvableRows->count(),
                'updated' => 0,
                'skipped' => 0,
                'skipped_candidates' => $skippedCandidates,
                'limit' => $limit,
                'preview' => $preview,
                'updated_rows' => [],
            ];
        }

        $updated = 0;
        $skipped = 0;
        $updatedRows = [];

        DB::transaction(function () use ($resolvableRows, &$updated, &$skipped, &$updatedRows): void {
            foreach ($resolvableRows as $row) {
                $saleItem = DB::table('sale_items')
                    ->where('tenant_id', $row->tenant_id)
                    ->where('sale_id', $row->sale_id)
                    ->where('product_id', $row->product_id)
                    ->when(
                        $row->product_variant_id,
                        fn ($query) => $query->where('product_variant_id', $row->product_variant_id),
                        fn ($query) => $query->whereNull('product_variant_id')
                    )
                    ->orderBy('id')
                    ->first(['id', 'product_variant_id', 'price', 'discount', 'qty', 'hpp_snapshot']);

                if (! $saleItem) {
                    $skipped++;
                    continue;
                }

                $locationId = DB::table('stock_ledger')
                    ->where('tenant_id', $row->tenant_id)
                    ->where('reference_type', 'sale')
                    ->where('reference_item_id', $saleItem->id)
                    ->orderBy('created_at')
                    ->value('location_id');

                $itemDiscountAmount = Decimal::money('0');
                if (Decimal::compare(Decimal::qty($saleItem->qty), '0') > 0 && Decimal::compare(Decimal::money($saleItem->discount), '0') > 0) {
                    $itemDiscountAmount = Decimal::multiply(
                        Decimal::divide($saleItem->discount, $saleItem->qty, Decimal::RATE_SCALE),
                        Decimal::qty($row->qty),
                        Decimal::MONEY_SCALE
                    );
                }

                $grossAmount = Decimal::money($row->amount ?? 0);
                $unitPriceSnapshot = Decimal::money($saleItem->price);
                $hppSnapshot = Decimal::cost($saleItem->hpp_snapshot ?? 0);
                $netAmount = Decimal::max(Decimal::subtract($grossAmount, $itemDiscountAmount, Decimal::MONEY_SCALE), '0', Decimal::MONEY_SCALE);
                $refundedCostTotal = Decimal::multiply($hppSnapshot, Decimal::qty($row->qty), Decimal::MONEY_SCALE);

                DB::table('refund_items')
                    ->where('tenant_id', $row->tenant_id)
                    ->where('id', $row->refund_item_id)
                    ->update([
                        'sale_item_id' => $saleItem->id,
                        'product_variant_id' => $row->product_variant_id ?: $saleItem->product_variant_id,
                        'location_id' => $row->location_id ?: $locationId,
                        'unit_price_snapshot' => Decimal::float($unitPriceSnapshot, Decimal::MONEY_SCALE),
                        'gross_amount' => Decimal::float($grossAmount, Decimal::MONEY_SCALE),
                        'item_discount_amount' => Decimal::float($itemDiscountAmount, Decimal::MONEY_SCALE),
                        'transaction_discount_amount' => 0,
                        'tax_amount' => 0,
                        'net_amount' => Decimal::float($netAmount, Decimal::MONEY_SCALE),
                        'hpp_snapshot' => Decimal::float($hppSnapshot, Decimal::COST_SCALE),
                        'refunded_cost_total' => Decimal::float($refundedCostTotal, Decimal::MONEY_SCALE),
                    ]);

                $updated++;
                $updatedRows[] = [
                    'refund_item_id' => $row->refund_item_id,
                    'refund_id' => $row->refund_id,
                    'sale_id' => $row->sale_id,
                    'branch_id' => $row->branch_id,
                    'branch_name' => $row->branch_name,
                    'product_id' => $row->product_id,
                    'qty' => Decimal::float(Decimal::qty($row->qty), Decimal::QTY_SCALE),
                    'amount' => Decimal::float(Decimal::money($row->amount ?? 0), Decimal::MONEY_SCALE),
                    'classification' => $this->classify($row),
                ];
            }
        }, 3);

        return [
            'requested_count' => $requestedIds->count(),
            'matched_candidates' => $candidateRows->count(),
            'matched_resolvable' => $resolvableRows->count(),
            'updated' => $updated,
            'skipped' => $skipped,
            'skipped_candidates' => $skippedCandidates,
            'limit' => $limit,
            'preview' => $preview,
            'updated_rows' => $updatedRows,
        ];
    }
}