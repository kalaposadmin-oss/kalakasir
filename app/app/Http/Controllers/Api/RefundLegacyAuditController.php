<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\RefundLegacyAuditService;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RefundLegacyAuditController extends Controller
{
    public function __construct(
        private readonly RefundLegacyAuditService $service,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function index(Request $request)
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json([
                'code' => 'TENANT_NOT_RESOLVED',
                'message' => 'Tenant tidak ditemukan.',
                'details' => [],
            ], 400);
        }

        $validated = $this->validatedFilters($request);

        $rows = $this->service->rows($tenantId, $validated);
        $classification = $validated['classification'] ?? null;
        if ($classification) {
            $rows = $rows->filter(fn ($row) => $this->service->classify($row) === $classification)->values();
        }

        $page = max((int) ($validated['page'] ?? 1), 1);
        $perPage = (int) ($validated['per_page'] ?? ($validated['limit'] ?? 100));
        $summary = $this->service->summary($rows);
        $total = $rows->count();
        $pagedRows = $rows->forPage($page, $perPage)->values();
        $lastPage = max((int) ceil(max($total, 1) / max($perPage, 1)), 1);

        return response()->json([
            'rows' => $pagedRows->map(function ($row) {
                return [
                    'tenant_id' => $row->tenant_id,
                    'refund_id' => $row->refund_id,
                    'refund_item_id' => $row->refund_item_id,
                    'sale_id' => $row->sale_id,
                    'branch_id' => $row->branch_id,
                    'branch_name' => $row->branch_name,
                    'product_id' => $row->product_id,
                    'qty' => (float) $row->qty,
                    'amount' => (float) ($row->amount ?? 0),
                    'sale_item_match_count' => (int) $row->sale_item_match_count,
                    'classification' => $this->service->classify($row),
                    'refund_created_at' => $row->refund_created_at,
                ];
            })->values(),
            'summary' => $summary,
            'count' => $pagedRows->count(),
            'total' => $total,
            'limit' => $perPage,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => $lastPage,
        ]);
    }

    public function history(Request $request)
    {
        $tenantId = TenantContext::id();
        abort_unless($tenantId, 400, 'Tenant tidak ditemukan.');

        $validated = $request->validate([
            'actor_id' => ['nullable', 'string', 'max:64'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $page = max((int) ($validated['page'] ?? 1), 1);
        $perPage = (int) ($validated['per_page'] ?? 10);
        $history = $this->service->history($tenantId, $validated, $page, $perPage);
        $lastPage = max((int) ceil(max($history['total'], 1) / max($perPage, 1)), 1);

        return response()->json([
            'entries' => $history['entries'],
            'total' => $history['total'],
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => $lastPage,
        ]);
    }

    public function exportHistory(Request $request): StreamedResponse
    {
        $tenantId = TenantContext::id();
        abort_unless($tenantId, 400, 'Tenant tidak ditemukan.');

        $validated = $request->validate([
            'actor_id' => ['nullable', 'string', 'max:64'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);

        return $this->service->exportHistoryCsv($tenantId, $validated);
    }

    public function export(Request $request): StreamedResponse
    {
        $tenantId = TenantContext::id();
        abort_unless($tenantId, 400, 'Tenant tidak ditemukan.');

        $validated = $this->validatedFilters($request);
        $classification = $validated['classification'] ?? null;
        $rows = $this->service->rows($tenantId, $validated);
        if (in_array($classification, ['resolvable_single_match', 'ambiguous_multi_match', 'orphan_no_match'], true)) {
            $rows = $rows->filter(fn ($row) => $this->service->classify($row) === $classification)->values();
        }

        $filename = 'refund-legacy-audit-'.$tenantId.'.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, [
                'tenant_id',
                'refund_id',
                'refund_item_id',
                'sale_id',
                'branch_id',
                'branch_name',
                'product_id',
                'qty',
                'amount',
                'sale_item_match_count',
                'classification',
                'refund_created_at',
            ]);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->tenant_id,
                    $row->refund_id,
                    $row->refund_item_id,
                    $row->sale_id,
                    $row->branch_id,
                    $row->branch_name,
                    $row->product_id,
                    (float) $row->qty,
                    (float) ($row->amount ?? 0),
                    (int) $row->sale_item_match_count,
                    $this->service->classify($row),
                    $row->refund_created_at,
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function remediate(Request $request)
    {
        $tenantId = TenantContext::id();
        abort_unless($tenantId, 400, 'Tenant tidak ditemukan.');

        $validated = $request->validate([
            'classification' => ['nullable', 'in:resolvable_single_match,ambiguous_multi_match,orphan_no_match'],
            'branch_id' => ['nullable', 'string', 'max:64'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'dry_run' => ['nullable', 'boolean'],
            'refund_item_ids' => ['nullable', 'array', 'max:200'],
            'refund_item_ids.*' => ['string', 'max:64'],
        ]);

        $result = $this->service->remediate(
            $tenantId,
            $validated,
            $validated['refund_item_ids'] ?? [],
            (int) ($validated['limit'] ?? 1000),
            ! ($validated['dry_run'] ?? false)
        );

        if (! ($validated['dry_run'] ?? false) && ($result['updated'] ?? 0) > 0) {
            foreach ($result['updated_rows'] ?? [] as $row) {
                $this->auditLogger->log(
                    'refund_legacy_remediated',
                    $request->user()?->id,
                    'refund_item',
                    $row['refund_item_id'],
                    [
                        'refund_item_id' => $row['refund_item_id'],
                        'refund_id' => $row['refund_id'],
                        'sale_id' => $row['sale_id'],
                        'branch_id' => $row['branch_id'],
                        'product_id' => $row['product_id'],
                        'qty' => $row['qty'],
                        'amount' => $row['amount'],
                        'classification' => $row['classification'],
                        'requested_count' => $result['requested_count'] ?? 0,
                        'matched_candidates' => $result['matched_candidates'] ?? 0,
                        'matched_resolvable' => $result['matched_resolvable'] ?? 0,
                        'updated' => $result['updated'] ?? 0,
                        'skipped' => $result['skipped'] ?? 0,
                        'skipped_candidates' => $result['skipped_candidates'] ?? 0,
                        'filters' => [
                            'classification' => $validated['classification'] ?? null,
                            'branch_id' => $validated['branch_id'] ?? null,
                            'date_from' => $validated['date_from'] ?? null,
                            'date_to' => $validated['date_to'] ?? null,
                        ],
                    ],
                    $request->ip(),
                    $request->userAgent(),
                    $tenantId,
                );
            }
        }

        return response()->json([
            'mode' => ($validated['dry_run'] ?? false) ? 'dry_run' : 'execute',
            'requested_count' => $result['requested_count'],
            'matched_candidates' => $result['matched_candidates'],
            'matched_resolvable' => $result['matched_resolvable'],
            'updated' => $result['updated'],
            'skipped' => $result['skipped'],
            'skipped_candidates' => $result['skipped_candidates'],
            'limit' => $result['limit'],
            'preview' => $result['preview'],
            'updated_rows' => $result['updated_rows'],
        ]);
    }

    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'classification' => ['nullable', 'in:resolvable_single_match,ambiguous_multi_match,orphan_no_match'],
            'branch_id' => ['nullable', 'string', 'max:64'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
    }
}