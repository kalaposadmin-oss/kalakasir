<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\MissingSalesCostAggregateException;
use App\Http\Controllers\Controller;
use App\Services\DailyFinanceControlService;
use App\Services\SystemHealthEventService;
use App\Support\GovernanceRequestContext;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FinanceControlDashboardController extends Controller
{
    public function __construct(private readonly DailyFinanceControlService $service)
    {
    }

    public function index(Request $request)
    {
        $startedAt = microtime(true);
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json([
                'code' => 'TENANT_NOT_RESOLVED',
                'message' => 'Tenant tidak ditemukan.',
                'details' => [],
            ], 400);
        }

        $validated = $request->validate([
            'date' => ['nullable', 'date'],
        ]);

        $date = isset($validated['date'])
            ? CarbonImmutable::parse($validated['date'], 'UTC')->toDateString()
            : CarbonImmutable::now('UTC')->toDateString();

        try {
            $today = $this->service->calculate($tenantId, $date);
            $weekStart = CarbonImmutable::parse($date, 'UTC')->subDays(6)->toDateString();
            $weekRows = $this->service->readRange($tenantId, $weekStart, $date);

            $weekly = [
                'sales_total' => round(collect($weekRows)->sum('sales_total'), 2),
                'cogs_total' => round(collect($weekRows)->sum('cogs_total'), 2),
                'cash_closing_total' => round(collect($weekRows)->sum('cash_closing_total'), 2),
                'debt_due_7d_max' => round(collect($weekRows)->max('debt_due_7d') ?? 0, 2),
                'cash_difference_total' => round(collect($weekRows)->sum('cash_difference_total'), 2),
                'shift_closed_count' => (int) collect($weekRows)->sum('shift_closed_count'),
                'transaction_count' => (int) collect($weekRows)->sum('transaction_count'),
            ];

            return response()->json([
                'date' => $date,
                'kpi_today' => $today,
                'kpi_week' => $weekly,
                'snapshots' => $weekRows,
            ], 200, $this->financeResponseHeaders($startedAt, true));
        } catch (MissingSalesCostAggregateException $exception) {
            app(SystemHealthEventService::class)->recordOrBump('strict_reader_blocked', [
                'tenant_id' => $tenantId,
                'entity_type' => 'report_endpoint',
                'entity_id' => 'finance-control-daily:index',
                'summary' => 'Strict reader blocked finance control daily index because aggregate COGS is invalid.',
                'payload' => [
                    'endpoint' => 'finance-control-daily:index',
                    'query' => $request->query(),
                    'details' => $exception->details(),
                ],
                'source' => 'finance-control-dashboard-controller',
                'dedupe_key' => 'strict_reader_blocked:'.$tenantId.':finance-control-daily:index:'.sha1(json_encode($request->query(), JSON_UNESCAPED_SLASHES)),
            ]);

            return response()->json([
                'code' => 'SALES_COST_AGGREGATE_REQUIRED',
                'message' => $exception->getMessage(),
                'details' => $exception->details(),
            ], 409, $this->financeResponseHeaders($startedAt, true));
        }
    }

    public function snapshot(Request $request)
    {
        $startedAt = microtime(true);
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json([
                'code' => 'TENANT_NOT_RESOLVED',
                'message' => 'Tenant tidak ditemukan.',
                'details' => [],
            ], 400);
        }

        $validated = $request->validate([
            'date' => ['nullable', 'date'],
            ...GovernanceRequestContext::validationRules(),
        ]);

        try {
            $governance = GovernanceRequestContext::fromRequest($request, $validated);
            GovernanceRequestContext::enforceRoleConsistency($governance);
            GovernanceRequestContext::enforceApprovalPolicy($governance, 'finance_control_snapshot');

            $date = isset($validated['date'])
                ? CarbonImmutable::parse($validated['date'], 'UTC')->toDateString()
                : CarbonImmutable::now('UTC')->toDateString();

            $snapshot = $this->service->capture($tenantId, $date);

            return response()->json([
                'status' => 'snapshotted',
                'snapshot' => $snapshot,
                'governance' => GovernanceRequestContext::responseMeta($governance),
            ], 200, $this->financeResponseHeaders($startedAt, false));
        } catch (MissingSalesCostAggregateException $exception) {
            app(SystemHealthEventService::class)->recordOrBump('strict_reader_blocked', [
                'tenant_id' => $tenantId,
                'entity_type' => 'report_endpoint',
                'entity_id' => 'finance-control-daily:snapshot',
                'summary' => 'Strict reader blocked finance control snapshot because aggregate COGS is invalid.',
                'payload' => [
                    'endpoint' => 'finance-control-daily:snapshot',
                    'body' => $request->all(),
                    'details' => $exception->details(),
                ],
                'source' => 'finance-control-dashboard-controller',
                'dedupe_key' => 'strict_reader_blocked:'.$tenantId.':finance-control-daily:snapshot:'.sha1(json_encode($request->all(), JSON_UNESCAPED_SLASHES)),
            ]);

            return response()->json([
                'code' => 'SALES_COST_AGGREGATE_REQUIRED',
                'message' => $exception->getMessage(),
                'details' => $exception->details(),
            ], 409, $this->financeResponseHeaders($startedAt, false));
        } catch (ValidationException $exception) {
            return response()->json([
                'code' => 'VALIDATION_ERROR',
                'message' => 'Data tidak valid.',
                'details' => $exception->errors(),
            ], 422, $this->financeResponseHeaders($startedAt, false));
        }
    }

    public function export(Request $request)
    {
        $startedAt = microtime(true);
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json([
                'code' => 'TENANT_NOT_RESOLVED',
                'message' => 'Tenant tidak ditemukan.',
                'details' => [],
            ], 400);
        }

        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $toDate = isset($validated['to'])
            ? CarbonImmutable::parse($validated['to'], 'UTC')->toDateString()
            : CarbonImmutable::now('UTC')->toDateString();
        $fromDate = isset($validated['from'])
            ? CarbonImmutable::parse($validated['from'], 'UTC')->toDateString()
            : CarbonImmutable::parse($toDate, 'UTC')->subDays(6)->toDateString();

        try {
            $rows = $this->service->readRange($tenantId, $fromDate, $toDate);

            $lines = [
                'snapshot_date,sales_total,cogs_total,cash_closing_total,debt_due_7d,cash_difference_total,shift_closed_count,transaction_count',
            ];

            foreach ($rows as $row) {
                $lines[] = implode(',', [
                    $row['snapshot_date'],
                    $row['sales_total'],
                    $row['cogs_total'],
                    $row['cash_closing_total'],
                    $row['debt_due_7d'],
                    $row['cash_difference_total'],
                    $row['shift_closed_count'],
                    $row['transaction_count'],
                ]);
            }

            $csv = implode("\n", $lines);

            return response($csv, 200, array_merge([
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="finance-control-'.$fromDate.'-to-'.$toDate.'.csv"',
            ], $this->financeResponseHeaders($startedAt, true)));
        } catch (MissingSalesCostAggregateException $exception) {
            app(SystemHealthEventService::class)->recordOrBump('strict_reader_blocked', [
                'tenant_id' => $tenantId,
                'entity_type' => 'report_endpoint',
                'entity_id' => 'finance-control-daily:export',
                'summary' => 'Strict reader blocked finance control export because aggregate COGS is invalid.',
                'payload' => [
                    'endpoint' => 'finance-control-daily:export',
                    'query' => $request->query(),
                    'details' => $exception->details(),
                ],
                'source' => 'finance-control-dashboard-controller',
                'dedupe_key' => 'strict_reader_blocked:'.$tenantId.':finance-control-daily:export:'.sha1(json_encode($request->query(), JSON_UNESCAPED_SLASHES)),
            ]);

            return response()->json([
                'code' => 'SALES_COST_AGGREGATE_REQUIRED',
                'message' => $exception->getMessage(),
                'details' => $exception->details(),
            ], 409, $this->financeResponseHeaders($startedAt, true));
        }
    }

    private function financeResponseHeaders(float $startedAt, bool $readOnly): array
    {
        return [
            'Server-Timing' => 'finance-control;dur='.number_format((microtime(true) - $startedAt) * 1000, 2, '.', ''),
            'X-Finance-Read-Only' => $readOnly ? '1' : '0',
        ];
    }
}
