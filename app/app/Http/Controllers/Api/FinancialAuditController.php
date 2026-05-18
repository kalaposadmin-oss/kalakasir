<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FinancialAuditService;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinancialAuditController extends Controller
{
    public function __construct(private readonly FinancialAuditService $service)
    {
    }

    public function report(Request $request)
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json([
                'code' => 'TENANT_NOT_RESOLVED',
                'message' => 'Tenant tidak ditemukan.',
                'details' => [],
            ], 400);
        }

        $validated = $request->validate([
            'audit_date' => ['nullable', 'date'],
            'persist' => ['nullable', 'boolean'],
        ]);

        $auditDate = isset($validated['audit_date'])
            ? CarbonImmutable::parse($validated['audit_date'], 'UTC')->toDateString()
            : CarbonImmutable::now('UTC')->toDateString();

        $result = $this->service->run($tenantId, $auditDate);

        if ((bool) ($validated['persist'] ?? false)) {
            $this->service->persistAuditLog($tenantId, $result);
        }

        return response()->json($result);
    }

    public function dashboard(Request $request)
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json([
                'code' => 'TENANT_NOT_RESOLVED',
                'message' => 'Tenant tidak ditemukan.',
                'details' => [],
            ], 400);
        }

        $validated = $request->validate([
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date'],
        ]);

        $toDate = isset($validated['to_date'])
            ? CarbonImmutable::parse($validated['to_date'], 'UTC')->toDateString()
            : CarbonImmutable::now('UTC')->toDateString();
        $fromDate = isset($validated['from_date'])
            ? CarbonImmutable::parse($validated['from_date'], 'UTC')->toDateString()
            : CarbonImmutable::parse($toDate, 'UTC')->subDays(6)->toDateString();

        $latest = $this->service->run($tenantId, $toDate);

        $history = DB::table('finance_audit_logs')
            ->where('tenant_id', $tenantId)
            ->whereBetween('audit_date', [$fromDate, $toDate])
            ->orderBy('audit_date')
            ->get([
                'audit_date',
                'status',
                'inventory_vs_gl_difference',
                'cash_vs_gl_difference',
                'unbalanced_journal_count',
                'orphan_transaction_count',
            ])
            ->map(function ($row) {
                return [
                    'audit_date' => (string) $row->audit_date,
                    'status' => (string) $row->status,
                    'inventory_vs_gl_difference' => (float) $row->inventory_vs_gl_difference,
                    'cash_vs_gl_difference' => (float) $row->cash_vs_gl_difference,
                    'unbalanced_journal_count' => (int) $row->unbalanced_journal_count,
                    'orphan_transaction_count' => (int) $row->orphan_transaction_count,
                ];
            })
            ->values();

        $comparison = [
            'current' => [
                'audit_date' => $latest['audit_date'],
                'status' => $latest['status'],
                'mismatch_count' => $latest['summary']['mismatch_count'],
                'critical_count' => $latest['summary']['critical_count'],
            ],
            'previous' => null,
            'delta' => null,
        ];

        if ($history->count() >= 2) {
            $previous = $history->slice(-2, 1)->first();
            $current = $history->last();

            $comparison['previous'] = [
                'audit_date' => $previous['audit_date'],
                'status' => $previous['status'],
                'inventory_vs_gl_difference' => $previous['inventory_vs_gl_difference'],
                'cash_vs_gl_difference' => $previous['cash_vs_gl_difference'],
                'unbalanced_journal_count' => $previous['unbalanced_journal_count'],
                'orphan_transaction_count' => $previous['orphan_transaction_count'],
            ];

            $comparison['delta'] = [
                'inventory_vs_gl_difference' => round($current['inventory_vs_gl_difference'] - $previous['inventory_vs_gl_difference'], 2),
                'cash_vs_gl_difference' => round($current['cash_vs_gl_difference'] - $previous['cash_vs_gl_difference'], 2),
                'unbalanced_journal_count' => $current['unbalanced_journal_count'] - $previous['unbalanced_journal_count'],
                'orphan_transaction_count' => $current['orphan_transaction_count'] - $previous['orphan_transaction_count'],
            ];
        }

        return response()->json([
            'system_financial_status' => $latest['status'],
            'indicator' => [
                'green' => $latest['status'] === 'green',
                'yellow' => $latest['status'] === 'yellow',
                'red' => $latest['status'] === 'red',
            ],
            'latest' => $latest,
            'history' => $history,
            'historical_comparison' => $comparison,
        ]);
    }

    public function history(Request $request)
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json([
                'code' => 'TENANT_NOT_RESOLVED',
                'message' => 'Tenant tidak ditemukan.',
                'details' => [],
            ], 400);
        }

        $validated = $request->validate([
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:120'],
        ]);

        $toDate = isset($validated['to_date'])
            ? CarbonImmutable::parse($validated['to_date'], 'UTC')->toDateString()
            : CarbonImmutable::now('UTC')->toDateString();
        $fromDate = isset($validated['from_date'])
            ? CarbonImmutable::parse($validated['from_date'], 'UTC')->toDateString()
            : CarbonImmutable::parse($toDate, 'UTC')->subDays(30)->toDateString();
        $limit = (int) ($validated['limit'] ?? 60);

        $rows = DB::table('finance_audit_logs')
            ->where('tenant_id', $tenantId)
            ->whereBetween('audit_date', [$fromDate, $toDate])
            ->orderByDesc('audit_date')
            ->limit($limit)
            ->get();

        return response()->json([
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'count' => (int) $rows->count(),
            'logs' => $rows,
        ]);
    }
}
