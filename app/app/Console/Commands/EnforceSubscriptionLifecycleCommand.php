<?php

namespace App\Console\Commands;

use App\Services\SubscriptionService;
use App\Support\FeatureGateConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EnforceSubscriptionLifecycleCommand extends Command
{
    protected $signature = 'subscriptions:enforce-lifecycle {--tenant_id=}';

    protected $description = 'Enforce subscription lifecycle status transitions based on trial, grace period, and invoice payment status.';

    public function handle(): int
    {
        $tenantId = (string) $this->option('tenant_id');

        $appliedPending = app(SubscriptionService::class)->applyDuePendingPlanChanges($tenantId !== '' ? $tenantId : null);

        $query = DB::table('subscriptions')
            ->where('is_current', true)
            ->orderBy('started_at');

        if ($tenantId !== '') {
            $query->where('tenant_id', $tenantId);
        }

        $subscriptions = $query->get(['id', 'tenant_id', 'status', 'trial_ends_at', 'grace_ends_at']);

        $changed = 0;
        $suspendedCount = 0;
        $now = now('UTC');

        foreach ($subscriptions as $row) {
            $didChange = DB::transaction(function () use ($row, $now, &$suspendedCount): bool {
                $locked = DB::table('subscriptions')
                    ->where('id', $row->id)
                    ->lockForUpdate()
                    ->first(['id', 'tenant_id', 'status', 'trial_ends_at', 'grace_ends_at']);

                if (! $locked) {
                    return false;
                }

                $status = strtolower((string) $locked->status);
                $hasOverdueInvoice = DB::table('invoices')
                    ->where('tenant_id', $locked->tenant_id)
                    ->whereIn('status', ['unpaid', 'partial'])
                    ->whereNotNull('due_at')
                    ->where('due_at', '<=', $now)
                    ->exists();

                $nextStatus = $status;
                $nextGraceEndsAt = $locked->grace_ends_at;

                if ($status === 'trialing' && $locked->trial_ends_at !== null && (string) $locked->trial_ends_at <= (string) $now) {
                    $nextStatus = $hasOverdueInvoice ? 'past_due' : 'active';
                    $nextGraceEndsAt = $hasOverdueInvoice ? $now->copy()->addDays(3) : null;
                } elseif ($status === 'active' && $hasOverdueInvoice) {
                    $nextStatus = 'past_due';
                    $nextGraceEndsAt = $now->copy()->addDays(3);
                } elseif ($status === 'past_due' && ! $hasOverdueInvoice) {
                    $nextStatus = 'active';
                    $nextGraceEndsAt = null;
                } elseif (
                    $status === 'past_due'
                    && $hasOverdueInvoice
                    && $locked->grace_ends_at !== null
                    && (string) $locked->grace_ends_at <= (string) $now
                ) {
                    $nextStatus = 'suspended';
                } elseif ($status === 'suspended' && ! $hasOverdueInvoice) {
                    $nextStatus = 'active';
                    $nextGraceEndsAt = null;
                }

                if ($nextStatus === $status && (string) $nextGraceEndsAt === (string) $locked->grace_ends_at) {
                    return false;
                }

                DB::table('subscriptions')
                    ->where('id', $locked->id)
                    ->update([
                        'status' => $nextStatus,
                        'grace_ends_at' => $nextGraceEndsAt,
                        'updated_at' => $now,
                    ]);

                if ($nextStatus === 'suspended') {
                    if ($status !== 'suspended') {
                        $suspendedCount++;
                    }

                    DB::table('tenants')
                        ->where('id', $locked->tenant_id)
                        ->update(['plan' => 'S']);
                    FeatureGateConfig::forgetEntitlements('S');
                }

                return true;
            }, 3);

            if ($didChange) {
                $changed++;
            }
        }

        Log::info('metric.subscription.suspended', [
            'count' => $suspendedCount,
            'tenant_id' => $tenantId !== '' ? $tenantId : null,
            'source' => 'lifecycle_enforcer',
        ]);

        $this->info("Lifecycle enforcement selesai. subscriptions changed: {$changed}, pending plan applied: {$appliedPending}");

        return self::SUCCESS;
    }
}
