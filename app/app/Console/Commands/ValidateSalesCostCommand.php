<?php

namespace App\Console\Commands;

use App\Services\SalesCostReadinessService;
use App\Services\SystemHealthEventService;
use Illuminate\Console\Command;

class ValidateSalesCostCommand extends Command
{
    protected $signature = 'sales-cost:validate
        {--tenant= : Tenant ID}
        {--from= : Start business date}
        {--to= : End business date}';

    protected $description = 'Validate completed sales that must already have aggregated COGS before reports are opened.';

    public function handle(SalesCostReadinessService $service, SystemHealthEventService $events): int
    {
        $tenantId = (string) $this->option('tenant');
        $from = (string) $this->option('from');
        $to = (string) $this->option('to');
        $scopeId = sha1(($tenantId !== '' ? $tenantId : 'all').'|'.$from.'|'.$to.'|validate');

        try {
            $summary = $service->validationSummary(
                tenantId: $tenantId,
                from: $from,
                to: $to,
            );

            $this->line('completed_sales: '.$summary['completed_sales']);
            $this->line('missing_component_sales: '.$summary['missing_component_sales']);
            $this->line('missing_header_cogs: '.$summary['missing_header_cogs']);
            $this->line('mismatched_header_cogs: '.$summary['mismatched_header_cogs']);

            if ($summary['sample_sale_ids'] !== []) {
                $this->line('sample_sale_ids: '.implode(', ', $summary['sample_sale_ids']));
            }

            if ($summary['invalid_sales'] > 0) {
                $events->recordOrBump('sales_cost_validate_failed', [
                    'tenant_id' => $tenantId !== '' ? $tenantId : null,
                    'entity_type' => 'sales_cost_scope',
                    'entity_id' => $scopeId,
                    'summary' => 'sales-cost:validate found invalid aggregate COGS state.',
                    'payload' => [
                        'scope' => ['tenant_id' => $tenantId ?: null, 'from' => $from ?: null, 'to' => $to ?: null],
                        'summary' => $summary,
                    ],
                    'source' => 'sales-cost:validate',
                    'dedupe_key' => 'sales_cost_validate_failed:'.$scopeId,
                ]);

                $this->error('Sales cost validation gagal. Jalankan sales-cost:rebuild sebelum membuka laporan strict aggregate-only.');

                return self::FAILURE;
            }

            $events->resolveMatching('sales_cost_validate_failed', [
                'tenant_id' => $tenantId !== '' ? $tenantId : null,
                'entity_type' => 'sales_cost_scope',
                'entity_id' => $scopeId,
            ], 'validation clean');

            $this->info('Sales cost validation lulus. Semua sale completed pada scope ini sudah memakai aggregate COGS.');

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $events->recordOrBump('sales_cost_validate_failed', [
                'tenant_id' => $tenantId !== '' ? $tenantId : null,
                'entity_type' => 'sales_cost_scope',
                'entity_id' => $scopeId,
                'summary' => 'sales-cost:validate failed unexpectedly.',
                'payload' => [
                    'scope' => ['tenant_id' => $tenantId ?: null, 'from' => $from ?: null, 'to' => $to ?: null],
                    'message' => $exception->getMessage(),
                ],
                'source' => 'sales-cost:validate',
                'dedupe_key' => 'sales_cost_validate_failed:'.$scopeId,
            ]);

            throw $exception;
        }
    }
}