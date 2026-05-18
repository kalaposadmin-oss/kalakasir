<?php

namespace App\Console\Commands;

use App\Services\SalesCostReadinessService;
use App\Services\SystemHealthEventService;
use Illuminate\Console\Command;

class SystemHealthScanSalesCostCommand extends Command
{
    protected $signature = 'system-health:scan-sales-cost
        {--tenant= : Tenant ID}
        {--from= : Start business date}
        {--to= : End business date}';

    protected $description = 'Scan aggregate-only sales cost integrity and record monitoring events.';

    public function handle(SalesCostReadinessService $readiness, SystemHealthEventService $events): int
    {
        $tenantId = (string) $this->option('tenant');
        $from = (string) $this->option('from');
        $to = (string) $this->option('to');
        $scopeId = sha1(($tenantId !== '' ? $tenantId : 'all').'|'.$from.'|'.$to.'|sales-cost-scan');
        $summary = $readiness->validationSummary($tenantId !== '' ? $tenantId : null, $from !== '' ? $from : null, $to !== '' ? $to : null);

        $this->line('completed_sales: '.$summary['completed_sales']);
        $this->line('missing_component_sales: '.$summary['missing_component_sales']);
        $this->line('missing_header_cogs: '.$summary['missing_header_cogs']);
        $this->line('mismatched_header_cogs: '.$summary['mismatched_header_cogs']);

        if ($summary['missing_component_sales'] > 0) {
            $events->recordOrBump('sales_cost_missing_aggregate', [
                'tenant_id' => $tenantId !== '' ? $tenantId : null,
                'entity_type' => 'sales_cost_scope',
                'entity_id' => $scopeId,
                'summary' => 'Detected completed sales without aggregate COGS in scheduled scan.',
                'payload' => [
                    'scope' => ['tenant_id' => $tenantId ?: null, 'from' => $from ?: null, 'to' => $to ?: null],
                    'summary' => $summary,
                    'context' => 'scheduled_scan',
                ],
                'source' => 'system-health:scan-sales-cost',
                'dedupe_key' => 'sales_cost_missing_aggregate:'.$scopeId,
            ]);
        } else {
            $events->resolveMatching('sales_cost_missing_aggregate', [
                'tenant_id' => $tenantId !== '' ? $tenantId : null,
                'entity_type' => 'sales_cost_scope',
                'entity_id' => $scopeId,
            ], 'scheduled scan clean');
        }

        if (($summary['missing_header_cogs'] + $summary['mismatched_header_cogs']) > 0) {
            $events->recordOrBump('sales_cost_header_mismatch', [
                'tenant_id' => $tenantId !== '' ? $tenantId : null,
                'entity_type' => 'sales_cost_scope',
                'entity_id' => $scopeId,
                'summary' => 'Detected sales.cogs_total mismatch against aggregate components in scheduled scan.',
                'payload' => [
                    'scope' => ['tenant_id' => $tenantId ?: null, 'from' => $from ?: null, 'to' => $to ?: null],
                    'summary' => $summary,
                    'context' => 'scheduled_scan',
                ],
                'source' => 'system-health:scan-sales-cost',
                'dedupe_key' => 'sales_cost_header_mismatch:'.$scopeId,
            ]);
        } else {
            $events->resolveMatching('sales_cost_header_mismatch', [
                'tenant_id' => $tenantId !== '' ? $tenantId : null,
                'entity_type' => 'sales_cost_scope',
                'entity_id' => $scopeId,
            ], 'scheduled scan clean');
        }

        if ($summary['invalid_sales'] > 0) {
            $events->recordOrBump('sales_cost_validate_failed', [
                'tenant_id' => $tenantId !== '' ? $tenantId : null,
                'entity_type' => 'sales_cost_scope',
                'entity_id' => $scopeId,
                'summary' => 'Daily sales cost validation scan failed.',
                'payload' => [
                    'scope' => ['tenant_id' => $tenantId ?: null, 'from' => $from ?: null, 'to' => $to ?: null],
                    'summary' => $summary,
                ],
                'source' => 'system-health:scan-sales-cost',
                'dedupe_key' => 'sales_cost_validate_failed:'.$scopeId,
            ]);

            $this->error('sales cost scan menemukan anomaly');

            return self::FAILURE;
        }

        $events->resolveMatching('sales_cost_validate_failed', [
            'tenant_id' => $tenantId !== '' ? $tenantId : null,
            'entity_type' => 'sales_cost_scope',
            'entity_id' => $scopeId,
        ], 'scheduled scan clean');

        $this->info('sales cost scan clean');

        return self::SUCCESS;
    }
}