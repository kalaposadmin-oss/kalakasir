<?php

namespace App\Services\Platform;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class PlatformDashboardService
{
    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function summary(array $filters): array
    {
        [$startAt, $endAt] = $this->resolveRange($filters);
        $now = CarbonImmutable::now('UTC');

        $tenantTotal = (int) DB::table('tenants')->count();
        $userTotal = (int) DB::table('users')->count();

        $subscriptionStatuses = DB::table('subscriptions')
            ->select(['tenant_id', 'status'])
            ->where('is_current', true)
            ->get()
            ->groupBy('tenant_id');

        $suspendedTenantIds = $subscriptionStatuses
            ->filter(function ($rows): bool {
                $status = strtolower((string) ($rows->first()->status ?? ''));

                return in_array($status, ['suspended', 'canceled'], true);
            })
            ->keys()
            ->all();

        $inactiveTenantIds = $subscriptionStatuses
            ->filter(function ($rows): bool {
                $status = strtolower((string) ($rows->first()->status ?? ''));

                return $status === 'canceled';
            })
            ->keys()
            ->all();

        $overdueTenantIds = DB::table('invoices')
            ->select('tenant_id')
            ->whereNotNull('due_at')
            ->where('due_at', '<', $now)
            ->whereNotIn('status', ['paid', 'void'])
            ->distinct()
            ->pluck('tenant_id')
            ->all();

        $billingIssueCount = count($overdueTenantIds);
        $tenantSuspended = count($suspendedTenantIds);
        $tenantInactive = count($inactiveTenantIds);
        $tenantActive = max(0, $tenantTotal - $tenantSuspended);
        $tenantHealthy = max(0, $tenantTotal - $billingIssueCount);

        $overdueInvoices = (int) DB::table('invoices')
            ->whereNotNull('due_at')
            ->where('due_at', '<', $now)
            ->whereNotIn('status', ['paid', 'void'])
            ->count();

        $recentEvents = DB::table('subscription_audit_logs')
            ->whereBetween('created_at', [$startAt, $endAt])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get([
                'id',
                'tenant_id',
                'old_plan',
                'new_plan',
                'reason',
                'request_id',
                'created_at',
            ])
            ->map(function ($row) {
                $payload = (array) $row;
                if (! empty($payload['created_at'])) {
                    $payload['created_at'] = CarbonImmutable::parse($payload['created_at'], 'UTC')->toIso8601String();
                }

                return $payload;
            })
            ->all();

        return [
            'totals' => [
                'tenant_total' => $tenantTotal,
                'tenant_active' => $tenantActive,
                'tenant_suspended' => $tenantSuspended,
                'user_total' => $userTotal,
            ],
            'overdue_invoices' => $overdueInvoices,
            'recent_events' => $recentEvents,
            'health_summary' => [
                'healthy' => $tenantHealthy,
                'billing_issue' => $billingIssueCount,
                'inactive' => $tenantInactive,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function resolveRange(array $filters): array
    {
        $preset = (string) ($filters['preset'] ?? 'last7');
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;

        $today = CarbonImmutable::now('UTC')->startOfDay();

        return match ($preset) {
            'today' => [$today, $today->endOfDay()],
            'last30' => [$today->subDays(29), $today->endOfDay()],
            default => $this->resolveCustomOrDefault($from, $to, $today),
        };
    }

    private function resolveCustomOrDefault(?string $from, ?string $to, CarbonImmutable $today): array
    {
        if ($from || $to) {
            $start = CarbonImmutable::parse($from ?? $today->toDateString(), 'UTC')->startOfDay();
            $end = CarbonImmutable::parse($to ?? $from ?? $today->toDateString(), 'UTC')->endOfDay();

            if ($end < $start) {
                [$start, $end] = [$end, $start];
            }

            return [$start, $end];
        }

        return [$today->subDays(6), $today->endOfDay()];
    }
}
