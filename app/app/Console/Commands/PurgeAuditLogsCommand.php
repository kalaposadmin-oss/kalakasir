<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PurgeAuditLogsCommand extends Command
{
    protected $signature = 'audit:purge {--days=} {--force}';

    protected $description = 'Purge audit logs older than retention window.';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('platform.audit_log_retention_days', 365));
        $days = max(1, $days);

        if (! $this->option('force')) {
            $this->warn('Gunakan --force untuk menjalankan purge audit log.');
            return self::INVALID;
        }

        $cutoff = CarbonImmutable::now('UTC')->subDays($days);

        $auditCount = DB::table('audit_logs')->where('created_at', '<', $cutoff)->count();
        $subscriptionCount = DB::table('subscription_audit_logs')->where('created_at', '<', $cutoff)->count();

        DB::table('audit_logs')->where('created_at', '<', $cutoff)->delete();
        DB::table('subscription_audit_logs')->where('created_at', '<', $cutoff)->delete();

        $this->info('Purge audit log selesai.');
        $this->line('Audit logs deleted: '.$auditCount);
        $this->line('Subscription audit logs deleted: '.$subscriptionCount);
        $this->line('Retention days: '.$days);

        return self::SUCCESS;
    }
}
