<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Anomaly;
use App\Models\Branch;
use App\Models\DailySalesSummary;
use App\Models\Location;
use App\Models\Sale;
use App\Services\AnomalyDetectorMetricsService;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ObservabilityController extends Controller
{
    public function __invoke(Request $request)
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json([
                'code' => 'TENANT_NOT_RESOLVED',
                'message' => 'Tenant tidak ditemukan.',
                'details' => [],
            ], 400);
        }

        $filters = $request->validate([
            'preset' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'branch_id' => ['nullable', 'uuid'],
            'location_id' => ['nullable', 'uuid'],
        ]);

        $connection = config('queue.default');
        $queueDepth = null;
        $failedJobs5m = null;

        [$startUtc, $endExclusiveUtc, $rangeMeta] = $this->resolveRange(
            preset: $filters['preset'] ?? 'last7',
            startDate: $filters['start_date'] ?? null,
            endDate: $filters['end_date'] ?? null
        );

        $branchId = $filters['branch_id'] ?? null;
        $locationId = $filters['location_id'] ?? null;

        $anomalyWindowQuery = Anomaly::query()
            ->where('tenant_id', $tenantId)
            ->where('detected_at', '>=', $startUtc)
            ->where('detected_at', '<', $endExclusiveUtc)
            ->when($branchId, fn ($query, $value) => $query->where('payload_json->branch_id', $value))
            ->when($locationId, fn ($query, $value) => $query->where('payload_json->location_id', $value));

        $anomalyCountsBySeverity = (clone $anomalyWindowQuery)
            ->select('severity')
            ->selectRaw('count(*) as total')
            ->groupBy('severity')
            ->pluck('total', 'severity');

        $anomalyCountsByType = (clone $anomalyWindowQuery)
            ->select('type')
            ->selectRaw('count(*) as total')
            ->groupBy('type')
            ->orderByDesc('total')
            ->limit(20)
            ->pluck('total', 'type');

        $inventoryInvariantTypes = [
            'fifo_ledger_balance_drift',
            'fifo_layer_negative',
            'fifo_allocation_qty_mismatch',
            'fifo_allocation_cost_mismatch',
            'fifo_journal_cogs_mismatch',
            'fifo_void_ledger_mismatch',
            'fifo_void_allocation_not_reversed',
        ];

        $invariantCounts = (clone $anomalyWindowQuery)
            ->whereIn('type', $inventoryInvariantTypes)
            ->select('type')
            ->selectRaw('count(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $resolutionRows = (clone $anomalyWindowQuery)
            ->select('type')
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when resolved_at is not null then 1 else 0 end) as resolved_total')
            ->groupBy('type')
            ->get();

        $resolveRatioByType = [];
        foreach ($resolutionRows as $row) {
            $total = max(1, (int) $row->total);
            $resolveRatioByType[(string) $row->type] = round(((int) $row->resolved_total) / $total, 4);
        }

        $detectorMetrics = app(AnomalyDetectorMetricsService::class)->snapshot([
            'void_spike',
            'adjustment_spike',
            'invalid_transaction_date_spike',
            'void_split_attack',
        ]);

        $lag = $this->summaryLagP95Seconds($tenantId, $branchId, $locationId);

        $selectedBranch = $branchId
            ? Branch::query()->where('tenant_id', $tenantId)->where('id', $branchId)->value('name')
            : null;
        $selectedLocation = $locationId
            ? Location::query()->where('tenant_id', $tenantId)->where('id', $locationId)->value('name')
            : null;

        return response()->json([
            'queue' => [
                'connection' => $connection,
                'depth' => $queueDepth,
                'failed_jobs_5m' => $failedJobs5m,
            ],
            'summary_lag' => [
                'p95_seconds' => $lag,
            ],
            'anomalies' => [
                'window_hours' => (int) ($rangeMeta['window_hours'] ?? 0),
                'range' => $rangeMeta,
                'filters' => [
                    'branch_id' => $branchId,
                    'branch_name' => $selectedBranch,
                    'location_id' => $locationId,
                    'location_name' => $selectedLocation,
                ],
                'count_by_severity' => $anomalyCountsBySeverity,
                'count_by_type' => $anomalyCountsByType,
                'inventory_invariants' => $invariantCounts,
                'resolve_ratio_by_type' => $resolveRatioByType,
            ],
            'detectors' => [
                'metrics' => $detectorMetrics,
            ],
        ]);
    }

    private function summaryLagP95Seconds(string $tenantId, ?string $branchId = null, ?string $locationId = null): int
    {
        $rows = Sale::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->where('created_at', '>=', CarbonImmutable::now('UTC')->subMinutes(15))
            ->when($branchId, fn ($query, $value) => $query->where('branch_id', $value))
            ->limit(300)
            ->get(['tenant_id', 'created_at', 'transaction_date']);

        $lags = [];

        foreach ($rows as $row) {
            $summaryDate = $row->transaction_date
                ?: CarbonImmutable::parse($row->created_at)->toDateString();

            $summary = DailySalesSummary::query()
                ->where('tenant_id', $row->tenant_id)
                ->where('date', $summaryDate)
                ->first(['updated_at']);

            if (! $summary || ! $summary->updated_at) {
                continue;
            }

            $lags[] = CarbonImmutable::parse($summary->updated_at)->diffInSeconds(CarbonImmutable::parse($row->created_at));
        }

        if (empty($lags)) {
            return 0;
        }

        sort($lags);
        $idx = (int) floor((count($lags) - 1) * 0.95);

        return (int) ($lags[$idx] ?? 0);
    }

    private function resolveRange(string $preset, ?string $startDate, ?string $endDate): array
    {
        $timezone = 'Asia/Jakarta';
        $today = CarbonImmutable::now($timezone)->startOfDay();

        [$startLocal, $endLocal, $label] = match ($preset) {
            'today' => [$today, $today, 'Hari Ini'],
            'yesterday' => [$today->subDay(), $today->subDay(), 'Kemarin'],
            'last30', '30days' => [$today->subDays(29), $today, '30 Hari Terakhir'],
            'thisMonth' => [$today->startOfMonth(), $today->endOfMonth()->startOfDay(), 'Bulan Ini'],
            'lastMonth' => [
                $today->subMonthNoOverflow()->startOfMonth(),
                $today->subMonthNoOverflow()->endOfMonth()->startOfDay(),
                'Bulan Lalu',
            ],
            'custom' => $this->resolveCustomRange($startDate, $endDate, $today),
            'last7', '7days', 'last7days' => [$today->subDays(6), $today, '7 Hari Terakhir'],
            default => [$today->subDays(6), $today, '7 Hari Terakhir'],
        };

        $days = $startLocal->diffInDays($endLocal) + 1;
        $limited = false;
        if ($days > 90) {
            $endLocal = $startLocal->addDays(89);
            $limited = true;
        }

        $windowHours = $startLocal->diffInHours($endLocal->copy()->addDay()->startOfDay());

        return [
            $startLocal->setTimezone('UTC'),
            $endLocal->addDay()->startOfDay()->setTimezone('UTC'),
            [
                'preset' => $preset,
                'start_date' => $startLocal->toDateString(),
                'end_date' => $endLocal->toDateString(),
                'label' => $label,
                'timezone' => $timezone,
                'limited' => $limited,
                'limit_days' => 90,
                'window_hours' => $windowHours,
            ],
        ];
    }

    private function resolveCustomRange(?string $from, ?string $to, CarbonImmutable $today): array
    {
        $timezone = 'Asia/Jakarta';
        $fromLocal = CarbonImmutable::parse($from ?? $today->toDateString(), $timezone)->startOfDay();
        $toLocal = CarbonImmutable::parse($to ?? $from ?? $today->toDateString(), $timezone)->startOfDay();

        if ($toLocal < $fromLocal) {
            [$fromLocal, $toLocal] = [$toLocal, $fromLocal];
        }

        return [$fromLocal, $toLocal, 'Periode Kustom'];
    }
}
