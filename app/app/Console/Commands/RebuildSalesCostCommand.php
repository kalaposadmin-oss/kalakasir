<?php

namespace App\Console\Commands;

use App\Services\SalesCostAggregationService;
use App\Services\SystemHealthEventService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RebuildSalesCostCommand extends Command
{
    protected $signature = 'sales-cost:rebuild
        {--tenant= : Tenant ID}
        {--from= : Start transaction date}
        {--to= : End transaction date}
        {--chunk=500 : Chunk size}
        {--sync-journal : Resync sale journals after rebuild}';

    protected $description = 'Rebuild sale cost components and sales.cogs_total for completed sales.';

    public function handle(SalesCostAggregationService $service, SystemHealthEventService $events): int
    {
        $tenantId = (string) $this->option('tenant');
        $from = (string) $this->option('from');
        $to = (string) $this->option('to');
        $chunk = max(50, (int) $this->option('chunk'));
        $syncJournal = (bool) $this->option('sync-journal');
        $scopeId = sha1(($tenantId !== '' ? $tenantId : 'all').'|'.$from.'|'.$to.'|rebuild');

        $query = DB::table('sales')
            ->where('status', 'completed')
            ->when($tenantId !== '', fn ($builder) => $builder->where('tenant_id', $tenantId))
            ->when($from !== '', fn ($builder) => $builder->whereRaw('DATE(COALESCE(transaction_date, created_at)) >= ?', [$from]))
            ->when($to !== '', fn ($builder) => $builder->whereRaw('DATE(COALESCE(transaction_date, created_at)) <= ?', [$to]))
            ->orderBy('tenant_id')
            ->orderByRaw('DATE(COALESCE(transaction_date, created_at))')
            ->orderBy('id')
            ->select('tenant_id', 'id');

        try {
            $count = 0;
            $query->chunk($chunk, function ($rows) use ($service, $syncJournal, &$count) {
                foreach ($rows as $row) {
                    $service->recomputeForSale(
                        tenantId: (string) $row->tenant_id,
                        saleId: (string) $row->id,
                        source: 'rebuild'
                    );

                    if ($syncJournal) {
                        $service->syncSaleJournalForSale((string) $row->tenant_id, (string) $row->id);
                    }

                    $count++;
                    $this->line('recomputed '.$row->tenant_id.' '.$row->id);
                }
            });

            $events->resolveMatching('sales_cost_rebuild_failed', [
                'tenant_id' => $tenantId !== '' ? $tenantId : null,
                'entity_type' => 'sales_cost_scope',
                'entity_id' => $scopeId,
            ], 'rebuild succeeded');

            $this->info('Sales cost rebuild selesai. Total sale diproses: '.$count);
            $this->warn('Sesudah rebuild, refresh summary materialized per tanggal terdampak sebelum rollout penuh.');
            $this->line('Verifikasi hasil dengan sales-cost:validate memakai scope tenant/tanggal yang sama.');

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $events->recordOrBump('sales_cost_rebuild_failed', [
                'tenant_id' => $tenantId !== '' ? $tenantId : null,
                'entity_type' => 'sales_cost_scope',
                'entity_id' => $scopeId,
                'summary' => 'sales-cost:rebuild failed.',
                'payload' => [
                    'scope' => ['tenant_id' => $tenantId ?: null, 'from' => $from ?: null, 'to' => $to ?: null],
                    'chunk' => $chunk,
                    'sync_journal' => $syncJournal,
                    'message' => $exception->getMessage(),
                ],
                'source' => 'sales-cost:rebuild',
                'dedupe_key' => 'sales_cost_rebuild_failed:'.$scopeId,
            ]);

            throw $exception;
        }
    }
}