<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\DB;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use App\Models\Tenant;
use App\Services\InventoryValuationService;
use App\Services\AnomalyService;
use App\Services\ValuationRebuildStateService;
use App\Services\ValuationComparisonExportService;
use App\Services\ValuationAuditService;
use App\Jobs\MonitorQueueHealthJob;
use App\Jobs\ReconcileDailySalesJob;
use App\Jobs\ReconcileInventorySummaryJob;
use App\Jobs\DetectVoidSpikeJob;
use App\Jobs\DetectVoidSplitAttackJob;
use App\Jobs\DetectAdjustmentSpikeJob;
use App\Jobs\DetectTransferStuckJob;
use App\Jobs\DetectInvalidTransactionDateSpikeJob;
use App\Jobs\DetectValuationDriftJob;
use App\Jobs\DetectSupplierDebtDueJob;
use App\Jobs\DetectInventoryInvariantJob;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('transactions:backfill-transaction-date {--tenant_id=} {--confirm=}', function () {
    $tenantId = (string) $this->option('tenant_id');
    $confirm = (string) $this->option('confirm');

    if ($tenantId === '') {
        $this->warn('Wajib isi --tenant_id untuk mencegah backfill lintas tenant tanpa sengaja.');
        return;
    }

    if ($confirm !== 'BACKFILL_ACK') {
        $this->warn('Wajib isi --confirm=BACKFILL_ACK untuk menjalankan backfill historis.');
        return;
    }

    $tables = [
        'sales',
        'purchases',
        'purchase_returns',
        'stock_transfers',
        'stock_adjustments',
    ];

    $updated = 0;

    foreach ($tables as $table) {
        $count = DB::table($table)
            ->where('tenant_id', $tenantId)
            ->whereNull('transaction_date')
            ->update([
                'transaction_date' => DB::raw('DATE(created_at)'),
            ]);

        $updated += (int) $count;
        $this->info("{$table}: {$count} rows updated");
    }

    $this->info("Total updated rows: {$updated}");
})->purpose('Backfill transaction_date from created_at for historical rows');

Artisan::command('inventory:rebuild-valuation {--tenant_id=} {--confirm=}', function () {
    $tenantId = (string) $this->option('tenant_id');
    $confirm = (string) $this->option('confirm');

    if ($tenantId === '') {
        $this->warn('Wajib isi --tenant_id untuk rebuild valuation.');
        return;
    }

    if ($confirm !== 'REBUILD_ACK') {
        $this->warn('Wajib isi --confirm=REBUILD_ACK untuk menjalankan rebuild valuation.');
        return;
    }

    $service = app(InventoryValuationService::class);
    $state = app(ValuationRebuildStateService::class);
    $anomaly = app(AnomalyService::class);

    if ($state->isLocked($tenantId)) {
        $this->warn("Skip tenant {$tenantId}: rebuild lock masih aktif.");
        return;
    }

    $state->lock($tenantId, 3600, [
        'trigger' => 'manual_cli',
        'actor_id' => null,
    ]);
    $this->info("Rebuild valuation tenant: {$tenantId}");

    try {
        DB::transaction(function () use ($service, $tenantId) {
            $service->replayFromLedger($tenantId);
        }, 3);

        app(ValuationAuditService::class)->log($tenantId, 'valuation_rebuild_executed', [
            'source' => 'inventory:rebuild-valuation',
        ]);

        $state->unlock($tenantId, true, 'valuation rebuild success');
    } catch (\Throwable $exception) {
        $state->unlock($tenantId, false, $exception->getMessage());

        app(ValuationAuditService::class)->log($tenantId, 'valuation_rebuild_failed', [
            'source' => 'inventory:rebuild-valuation',
            'message' => $exception->getMessage(),
        ]);

        $anomaly->raise(
            tenantId: $tenantId,
            severity: 'high',
            type: 'valuation_rebuild_failed',
            payload: [
                'message' => $exception->getMessage(),
                'source' => 'inventory:rebuild-valuation',
            ]
        );

        throw $exception;
    }

    $this->info('Rebuild valuation selesai.');
})->purpose('Rebuild FIFO/Average valuation state and sale item HPP snapshots from stock ledger history');

Artisan::command('inventory:audit-valuation {--tenant_id=} {--limit=10}', function () {
    $tenantId = (string) $this->option('tenant_id');

    if ($tenantId === '') {
        $this->warn('Wajib isi --tenant_id untuk audit valuation.');
        return;
    }

    $limit = max(1, (int) $this->option('limit'));

    $sampleRows = DB::table('inventory_balances as ib')
        ->join('products as p', function ($join) {
            $join->on('ib.product_id', '=', 'p.id')
                ->on('ib.tenant_id', '=', 'p.tenant_id');
        })
        ->where('ib.tenant_id', $tenantId)
        ->select('ib.product_id', 'p.name')
        ->selectRaw('sum(ib.qty_on_hand) as qty_total')
        ->groupBy('ib.product_id', 'p.name')
        ->orderByDesc('qty_total')
        ->limit($limit)
        ->get();

    if ($sampleRows->isEmpty()) {
        $this->warn('Tidak ada data inventory_balances untuk diaudit.');
        $this->line('Hint: jalankan transaksi purchase/sale atau seed data terlebih dahulu.');
        return;
    }

    $method = DB::table('tenants')
        ->where('id', $tenantId)
        ->value('inventory_valuation_method') ?? 'average';

    $this->info('Tenant: '.$tenantId);
    $this->info('Method: '.$method);
    $this->newLine();

    foreach ($sampleRows as $row) {
        $this->line('Product: '.$row->name.' ('.$row->product_id.')');
        $this->line('  Qty total: '.(float) $row->qty_total);

        $stateRows = DB::table('inventory_cost_states')
            ->where('tenant_id', $tenantId)
            ->where('product_id', $row->product_id)
            ->get(['location_id', 'qty_on_hand', 'average_cost']);

        if ($stateRows->isEmpty()) {
            $this->line('  Cost states: -');
        } else {
            foreach ($stateRows as $state) {
                $value = (float) $state->qty_on_hand * (float) $state->average_cost;
                $this->line('  Location '.$state->location_id.' qty='.(float) $state->qty_on_hand.' avg='.(float) $state->average_cost.' value='.$value);
            }
        }

        $latestSale = DB::table('sale_items as si')
            ->join('sales as s', function ($join) {
                $join->on('si.sale_id', '=', 's.id')
                    ->on('si.tenant_id', '=', 's.tenant_id');
            })
            ->where('si.tenant_id', $tenantId)
            ->where('si.product_id', $row->product_id)
            ->where('s.status', 'completed')
            ->orderByDesc('s.created_at')
            ->orderByDesc('si.id')
            ->first(['si.id', 'si.hpp_snapshot', 'si.qty', 's.id as sale_id', 's.created_at']);

        if (! $latestSale) {
            $this->line('  Latest sale item: -');
        } else {
            $this->line('  Latest sale item: '.$latestSale->id.' sale='.$latestSale->sale_id.' qty='.(float) $latestSale->qty.' hpp_snapshot='.(float) $latestSale->hpp_snapshot.' at='.$latestSale->created_at);
        }

        $allocRows = DB::table('inventory_issue_allocations')
            ->where('tenant_id', $tenantId)
            ->where('product_id', $row->product_id)
            ->whereNull('reversed_at')
            ->count();

        $this->line('  Active allocations: '.(int) $allocRows);
        $this->newLine();
    }
})->purpose('Audit valuation consistency for sampled products (qty, cost state, latest sale HPP, allocations)');

Artisan::command('inventory:export-valuation-comparison {--tenant_id=} {--from=} {--to=} {--sku_prefix=} {--limit=200}', function () {
    $tenantId = (string) $this->option('tenant_id');

    if ($tenantId === '') {
        $this->warn('Wajib isi --tenant_id untuk export valuation.');
        return;
    }

    $limit = max(1, (int) $this->option('limit'));
    $result = app(ValuationComparisonExportService::class)->export(
        tenantId: $tenantId,
        fromDate: $this->option('from'),
        toDate: $this->option('to'),
        skuPrefix: $this->option('sku_prefix'),
        limit: $limit,
    );

    $this->info('Report CSV: '.$result['csv_path']);
    $this->info('Report MD: '.$result['md_path']);
    $this->info('Total SKU compared: '.$result['count']);
})->purpose('Export average vs FIFO HPP comparison report (CSV/MD) for latest sale items per SKU');

Artisan::command('inventory:benchmark-rebuild {--tenant_id=} {--iterations=3}', function () {
    $tenantId = (string) $this->option('tenant_id');
    $iterations = max(1, (int) $this->option('iterations'));

    if ($tenantId === '') {
        $this->warn('Wajib isi --tenant_id untuk benchmark rebuild.');
        return;
    }

    $service = app(InventoryValuationService::class);
    $durations = [];

    for ($i = 1; $i <= $iterations; $i++) {
        $start = microtime(true);
        $service->replayFromLedger($tenantId);
        $elapsed = (microtime(true) - $start) * 1000;
        $durations[] = $elapsed;
        $this->info('Iteration '.$i.': '.round($elapsed, 2).' ms');
    }

    $avg = array_sum($durations) / max(count($durations), 1);
    sort($durations);
    $p95 = $durations[(int) floor((count($durations) - 1) * 0.95)] ?? $avg;

    $this->info('Average: '.round($avg, 2).' ms');
    $this->info('P95: '.round($p95, 2).' ms');
})->purpose('Benchmark valuation rebuild replay duration for a tenant');

    Artisan::command('inventory:check-invariants', function () {
        $this->info('Menjalankan audit invariant FIFO...');

        $job = new DetectInventoryInvariantJob();
        $job->handle(app(AnomalyService::class));

        $this->info('Selesai. Cek anomalies untuk detail.');
    })->purpose('Jalankan pengecekan invariant FIFO secara manual');

Artisan::command('inventory:prune-valuation-reports {--days=}', function () {
    $days = (int) ($this->option('days') ?: config('services.ops_alerts.report_retention_days', 14));
    $deleted = app(ValuationComparisonExportService::class)->pruneExpiredReports($days);
    $this->info("Deleted old valuation reports: {$deleted}");
})->purpose('Prune old valuation comparison report files by retention days');

Artisan::command('inventory:rollback-valuation-method {--tenant_id=} {--to=average} {--confirm=}', function () {
    $tenantId = (string) $this->option('tenant_id');
    $target = (string) $this->option('to');
    $confirm = (string) $this->option('confirm');

    if (! in_array($target, ['average', 'fifo'], true)) {
        $this->warn('Option --to hanya boleh average atau fifo.');
        return;
    }

    if ($tenantId === '') {
        $this->warn('Wajib isi --tenant_id untuk rollback valuation method.');
        return;
    }

    if ($confirm !== 'ROLLBACK_ACK') {
        $this->warn('Wajib isi --confirm=ROLLBACK_ACK untuk rollback valuation method.');
        return;
    }

    $state = app(ValuationRebuildStateService::class);
    if ($state->isLocked($tenantId)) {
        $this->warn('Tenant sedang lock rebuild, rollback dibatalkan.');
        return;
    }

    $current = DB::table('tenants')->where('id', $tenantId)->value('inventory_valuation_method') ?? 'average';
    if ($current === $target) {
        $this->info('Metode tenant sudah '.$target.'. Tidak ada perubahan.');
        return;
    }

    $state->lock($tenantId, 3600, [
        'request_id' => (string) \Illuminate\Support\Str::uuid(),
        'actor_id' => null,
        'trigger' => 'rollback_command',
    ]);

    try {
        DB::transaction(function () use ($tenantId, $target): void {
            DB::table('tenants')->where('id', $tenantId)->update([
                'inventory_valuation_method' => $target,
            ]);

            app(InventoryValuationService::class)->replayFromLedger($tenantId);
        }, 3);

        app(ValuationAuditService::class)->log($tenantId, 'valuation_method_rolled_back', [
            'from' => $current,
            'to' => $target,
            'source' => 'inventory:rollback-valuation-method',
        ]);

        app(AnomalyService::class)->raise(
            tenantId: $tenantId,
            severity: 'med',
            type: 'valuation_method_rollback',
            payload: [
                'from' => $current,
                'to' => $target,
                'source' => 'inventory:rollback-valuation-method',
            ]
        );

        $state->unlock($tenantId, true, 'valuation rollback success');
        $this->info("Rollback valuation selesai: {$current} -> {$target}");
    } catch (\Throwable $exception) {
        $state->unlock($tenantId, false, $exception->getMessage());
        throw $exception;
    }
})->purpose('Rollback valuation method for a tenant and replay ledger safely');

Artisan::command('inventory:prune-valuation-cache {--hours=168}', function () {
    $hours = max(1, (int) $this->option('hours'));
    $now = time();
    $threshold = $now - ($hours * 3600);

    $defaultStore = (string) config('cache.default');
    if ($defaultStore !== 'database') {
        $this->warn('Cache default bukan database, prune cache key pattern dilewati.');
        return;
    }

    $table = (string) config('cache.stores.database.table', 'cache');
    if (! \Illuminate\Support\Facades\Schema::hasTable($table)) {
        $this->warn('Tabel cache tidak ditemukan.');
        return;
    }

    $deleted = DB::table($table)
        ->where(function ($query) {
            $query->where('key', 'like', '%valuation:export:request:%')
                ->orWhere('key', 'like', '%valuation:rebuild:request:%')
                ->orWhere('key', 'like', '%valuation:%idempotency:%');
        })
        ->where('expiration', '<=', $threshold)
        ->delete();

    $this->info('Deleted valuation cache keys: '.$deleted);
})->purpose('Prune stale valuation async/idempotency cache keys from database cache store');

Schedule::job(new MonitorQueueHealthJob())->everyMinute();
Schedule::job(new ReconcileDailySalesJob())->dailyAt('00:30');
Schedule::job(new ReconcileInventorySummaryJob())->weeklyOn(1, '01:00');
Schedule::job(new DetectVoidSpikeJob())->hourly();
Schedule::job(new DetectVoidSplitAttackJob())->everyFifteenMinutes();
Schedule::job(new DetectAdjustmentSpikeJob())->hourly();
Schedule::job(new DetectTransferStuckJob())->hourly();
Schedule::job(new DetectInvalidTransactionDateSpikeJob())->everyFifteenMinutes();
Schedule::job(new DetectValuationDriftJob())->hourly();
Schedule::job(new DetectInventoryInvariantJob())->hourly();
Schedule::job(new DetectSupplierDebtDueJob())->dailyAt('08:00');
Schedule::command('inventory:prune-valuation-reports')->dailyAt('02:15');
Schedule::command('inventory:prune-valuation-cache')->dailyAt('02:25');
