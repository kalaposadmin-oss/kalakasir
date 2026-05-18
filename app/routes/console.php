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
use App\Services\RefundLegacyAuditService;
use App\Services\PurchaseHistoryBackfillService;
use App\Services\ValuationRebuildStateService;
use App\Services\ValuationComparisonExportService;
use App\Services\ValuationAuditService;
use App\Services\PurchaseJournalRepairService;
use App\Services\ShiftLifecycleService;
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
use App\Jobs\RunFinancialConsistencyAuditJob;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('pos:audit-consistency {--tenant_id=}', function () {
    $tenantId = (string) $this->option('tenant_id');

    $productQuery = DB::table('products')
        ->when($tenantId !== '', function ($query) use ($tenantId): void {
            $query->where('tenant_id', $tenantId);
        });

    $itemTypeIsNull = (clone $productQuery)
        ->whereNull('item_type')
        ->count();

    $modeAddOnNotRawMaterial = (clone $productQuery)
        ->where('product_mode', 'add_on')
        ->where(function ($query): void {
            $query->whereNull('item_type')
                ->orWhere('item_type', '!=', 'raw_material');
        })
        ->count();

    $modeBomNotFinishedGood = (clone $productQuery)
        ->where('product_mode', 'bom')
        ->where(function ($query): void {
            $query->whereNull('item_type')
                ->orWhere('item_type', '!=', 'finished_good');
        })
        ->count();

    $scope = $tenantId !== '' ? $tenantId : 'ALL';

    $this->info('POS consistency audit scope: '.$scope);
    $this->line('item_type_is_null: '.$itemTypeIsNull);
    $this->line('mode_add_on_not_raw_material: '.$modeAddOnNotRawMaterial);
    $this->line('mode_bom_not_finished_good: '.$modeBomNotFinishedGood);
})->purpose('Audit product item_type consistency indicators for POS catalog migration readiness');

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

Artisan::command('refunds:audit-legacy-ambiguity {--tenant_id=} {--limit=10}', function () {
    $tenantId = (string) $this->option('tenant_id');
    $limit = max(1, (int) $this->option('limit'));
    $service = app(RefundLegacyAuditService::class);
    $rows = $service->rows($tenantId);

    $scope = $tenantId !== '' ? $tenantId : 'ALL';
    $resolvable = $rows->filter(fn ($row) => $service->classify($row) === 'resolvable_single_match')->values();
    $ambiguous = $rows->filter(fn ($row) => $service->classify($row) === 'ambiguous_multi_match')->values();
    $orphan = $rows->filter(fn ($row) => $service->classify($row) === 'orphan_no_match')->values();

    $this->info('Legacy refund audit scope: '.$scope);
    $this->line('legacy_missing_sale_item_id: '.$rows->count());
    $this->line('resolvable_single_match: '.$resolvable->count());
    $this->line('ambiguous_multi_match: '.$ambiguous->count());
    $this->line('orphan_no_match: '.$orphan->count());

    if ($tenantId === '' && $rows->isNotEmpty()) {
        $this->newLine();
        $this->info('Tenant summary');
        $this->table(
            ['tenant_id', 'legacy_missing_sale_item_id', 'resolvable_single_match', 'ambiguous_multi_match', 'orphan_no_match'],
            $service->tenantSummary($rows)->take($limit)->all()
        );
    }

    if ($ambiguous->isNotEmpty()) {
        $this->newLine();
        $this->warn('Ambiguous samples');
        $this->table(
            ['refund_item_id', 'refund_id', 'sale_id', 'product_id', 'qty', 'sale_item_match_count'],
            $ambiguous->take($limit)->map(fn ($row) => [
                'refund_item_id' => $row->refund_item_id,
                'refund_id' => $row->refund_id,
                'sale_id' => $row->sale_id,
                'product_id' => $row->product_id,
                'qty' => (float) $row->qty,
                'sale_item_match_count' => (int) $row->sale_item_match_count,
            ])->all()
        );
    }

    if ($orphan->isNotEmpty()) {
        $this->newLine();
        $this->warn('Orphan samples');
        $this->table(
            ['refund_item_id', 'refund_id', 'sale_id', 'product_id', 'qty', 'sale_item_match_count'],
            $orphan->take($limit)->map(fn ($row) => [
                'refund_item_id' => $row->refund_item_id,
                'refund_id' => $row->refund_id,
                'sale_id' => $row->sale_id,
                'product_id' => $row->product_id,
                'qty' => (float) $row->qty,
                'sale_item_match_count' => (int) $row->sale_item_match_count,
            ])->all()
        );
    }
})->purpose('Audit historical refund_items without sale_item_id and classify whether they are resolvable, ambiguous, or orphaned');

Artisan::command('refunds:export-legacy-ambiguity {--tenant_id=} {--path=} ', function () {
    $tenantId = (string) $this->option('tenant_id');
    $path = trim((string) $this->option('path'));
    $service = app(RefundLegacyAuditService::class);

    if ($path === '') {
        $suffix = $tenantId !== '' ? $tenantId : 'all';
        $path = storage_path('app/audits/refunds/refund-legacy-audit-'.$suffix.'.csv');
    }

    $rows = $service->rows($tenantId)->map(function ($row) use ($service) {
        return [
            'tenant_id' => $row->tenant_id,
            'refund_id' => $row->refund_id,
            'refund_item_id' => $row->refund_item_id,
            'sale_id' => $row->sale_id,
            'product_id' => $row->product_id,
            'qty' => (float) $row->qty,
            'amount' => (float) ($row->amount ?? 0),
            'sale_item_match_count' => (int) $row->sale_item_match_count,
            'classification' => $service->classify($row),
            'refund_created_at' => $row->refund_created_at,
        ];
    });

    if (! is_dir(dirname($path))) {
        mkdir(dirname($path), 0777, true);
    }

    $handle = fopen($path, 'wb');
    if ($handle === false) {
        $this->error('Gagal membuka path export: '.$path);
        return 1;
    }

    fputcsv($handle, [
        'tenant_id',
        'refund_id',
        'refund_item_id',
        'sale_id',
        'product_id',
        'qty',
        'amount',
        'sale_item_match_count',
        'classification',
        'refund_created_at',
    ]);

    foreach ($rows as $row) {
        fputcsv($handle, $row);
    }

    fclose($handle);

    $summaryPath = preg_replace('/\.csv$/', '-summary.csv', $path) ?: ($path.'-summary.csv');
    $summaryRows = $service->tenantSummary($service->rows($tenantId));
    $summaryHandle = fopen($summaryPath, 'wb');
    if ($summaryHandle !== false) {
        fputcsv($summaryHandle, [
            'tenant_id',
            'legacy_missing_sale_item_id',
            'resolvable_single_match',
            'ambiguous_multi_match',
            'orphan_no_match',
        ]);

        foreach ($summaryRows as $summaryRow) {
            fputcsv($summaryHandle, $summaryRow);
        }

        fclose($summaryHandle);
    }

    $this->info('Legacy refund audit CSV exported: '.$path);
    $this->line('summary_path: '.$summaryPath);
    $this->line('rows: '.$rows->count());
})->purpose('Export historical refund_items without sale_item_id to CSV with ambiguity classification');

Artisan::command('refunds:remediate-legacy-sale-item {--tenant_id=} {--limit=1000} {--confirm=}', function () {
    $tenantId = (string) $this->option('tenant_id');
    $limit = max(1, (int) $this->option('limit'));
    $confirm = (string) $this->option('confirm');
    $service = app(RefundLegacyAuditService::class);

    if ($tenantId === '') {
        $this->warn('Wajib isi --tenant_id untuk remediation refund legacy.');
        return;
    }

    $preview = $service->remediate($tenantId, [], [], $limit, false);

    if (($preview['matched_resolvable'] ?? 0) === 0) {
        $this->info('Tidak ada refund legacy resolvable untuk tenant ini.');
        return;
    }

    $this->info('Tenant: '.$tenantId);
    $this->line('resolvable_rows: '.$preview['matched_resolvable']);
    $this->line('limit: '.$limit);

    if ($confirm !== 'REMEDIATE_ACK') {
        $this->warn('Dry run only. Isi --confirm=REMEDIATE_ACK untuk menjalankan update.');
        $this->table(
            ['refund_item_id', 'refund_id', 'sale_id', 'product_id', 'qty'],
            collect($preview['preview'] ?? [])->take(10)->map(fn ($row) => [
                'refund_item_id' => $row['refund_item_id'],
                'refund_id' => $row['refund_id'],
                'sale_id' => $row['sale_id'],
                'product_id' => $row['product_id'],
                'qty' => (float) $row['qty'],
            ])->all()
        );
        return;
    }

    $result = $service->remediate($tenantId, [], [], $limit, true);

    $this->info('Updated rows: '.$result['updated']);
    $this->line('Skipped rows: '.$result['skipped']);
})->purpose('Backfill sale_item_id and economic snapshots for resolvable historical refund_items within a tenant');

Artisan::command('valuation:backfill-purchase-layer-source-items {--tenant_id=} {--limit=10} {--confirm=}', function () {
    $tenantId = trim((string) $this->option('tenant_id'));
    $limit = max(1, (int) $this->option('limit'));
    $confirm = trim((string) $this->option('confirm'));
    $service = app(PurchaseHistoryBackfillService::class);

    $scopeTenantId = $tenantId !== '' ? $tenantId : null;
    $audit = $service->audit($scopeTenantId);
    $apply = $confirm === 'BACKFILL_ACK';
    $result = $service->backfillPurchaseLayerSourceItems($scopeTenantId, $apply, 5000);

    $scope = $tenantId !== '' ? $tenantId : 'ALL';
    $this->info('Purchase layer source_item_id audit scope: '.$scope);

    $layerSummary = $audit['purchase_layers']['summary'];
    $returnSummary = $audit['purchase_return_items']['summary'];

    $this->line('legacy_purchase_layers_missing_source_item_id: '.$layerSummary['total']);
    $this->line('layers_resolved_exact_ledger: '.$layerSummary['resolved_exact_ledger']);
    $this->line('layers_resolved_single_purchase_item: '.$layerSummary['resolved_single_purchase_item']);
    $this->line('layers_legacy_ambiguous: '.$layerSummary['legacy_ambiguous']);
    $this->line('layers_orphan_no_match: '.$layerSummary['orphan_no_match']);
    $this->line('legacy_purchase_return_items_missing_purchase_item_id: '.$returnSummary['total']);
    $this->line('returns_resolved_single_purchase_item: '.$returnSummary['resolved_single_purchase_item']);
    $this->line('returns_legacy_ambiguous: '.$returnSummary['legacy_ambiguous']);
    $this->line('returns_orphan_no_match: '.$returnSummary['orphan_no_match']);

    if ($apply) {
        $this->line('layers_updated: '.$result['updated']);
    } else {
        $this->warn('Dry run only. Tambahkan --confirm=BACKFILL_ACK untuk apply backfill.');
    }

    $ambiguousLayers = collect($audit['purchase_layers']['rows'])
        ->where('status', 'legacy_ambiguous')
        ->take($limit)
        ->map(fn (array $row) => [
            'layer_id' => $row['id'],
            'purchase_id' => $row['source_id'],
            'product_id' => $row['product_id'],
            'candidate_count' => $row['candidate_count'] ?? 0,
        ])
        ->values();

    if ($ambiguousLayers->isNotEmpty()) {
        $this->newLine();
        $this->warn('Ambiguous purchase layer samples');
        $this->table(['layer_id', 'purchase_id', 'product_id', 'candidate_count'], $ambiguousLayers->all());
    }

    $ambiguousReturns = collect($audit['purchase_return_items']['rows'])
        ->where('status', 'legacy_ambiguous')
        ->take($limit)
        ->map(fn (array $row) => [
            'purchase_return_item_id' => $row['purchase_return_item_id'],
            'purchase_return_id' => $row['purchase_return_id'],
            'purchase_id' => $row['purchase_id'],
            'product_id' => $row['product_id'],
            'candidate_count' => $row['candidate_count'] ?? 0,
        ])
        ->values();

    if ($ambiguousReturns->isNotEmpty()) {
        $this->newLine();
        $this->warn('Ambiguous purchase return samples');
        $this->table(['purchase_return_item_id', 'purchase_return_id', 'purchase_id', 'product_id', 'candidate_count'], $ambiguousReturns->all());
    }
})->purpose('Audit and optionally backfill legacy purchase inventory_layers.source_item_id without guessing ambiguous history');

Artisan::command('purchase:repair-journals {--tenant_id=} {--from=} {--to=} {--limit=1000} {--confirm=}', function () {
    $tenantId = trim((string) $this->option('tenant_id'));
    $from = trim((string) $this->option('from'));
    $to = trim((string) $this->option('to'));
    $limit = max(1, (int) $this->option('limit'));
    $confirm = trim((string) $this->option('confirm'));
    $service = app(PurchaseJournalRepairService::class);

    $scope = $tenantId !== '' ? $tenantId : 'ALL';
    $audit = $service->audit($tenantId !== '' ? $tenantId : null, $from !== '' ? $from : null, $to !== '' ? $to : null, $limit);

    $this->info('Purchase journal repair scope: '.$scope);
    $this->line('purchase_rows: '.$audit['summary']['total']);
    $this->line('cash_mismatch_rows: '.$audit['summary']['cash_mismatch']);
    $this->line('tempo_mismatch_rows: '.$audit['summary']['tempo_mismatch']);
    $this->line('missing_journal_rows: '.$audit['summary']['missing_journal']);
    $this->line('missing_cash_account_rows: '.$audit['summary']['missing_cash_account']);
    $this->line('repairable_rows: '.$audit['summary']['repairable']);

    if ($confirm !== 'REPAIR_ACK') {
        $this->warn('Dry run only. Tambahkan --confirm=REPAIR_ACK untuk apply repair jurnal purchase historis.');
        return;
    }

    $result = $service->repair($tenantId !== '' ? $tenantId : null, $from !== '' ? $from : null, $to !== '' ? $to : null, $limit, true);

    $this->info('Updated rows: '.$result['updated']);
    $this->line('Skipped rows: '.$result['skipped']);
})->purpose('Audit and repair purchase journal credits so cash purchases credit cash and tempo purchases credit payable');

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

Artisan::command('shifts:auto-close-overnight', function () {
    $service = app(ShiftLifecycleService::class);

    Tenant::query()->orderBy('name')->get()->each(function (Tenant $tenant) use ($service): void {
        $service->autoCloseExpiredOpenShifts($tenant);
    });

    $this->info('Overnight shift auto-close scan completed.');
})->purpose('Auto-close shifts that passed local midnight and mark them for next-day cash reconciliation');

Schedule::job(new MonitorQueueHealthJob())->everyMinute();
Schedule::command('shifts:auto-close-overnight')->everyTenMinutes()->withoutOverlapping()->runInBackground();
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
Schedule::command('system-health:scan-sales-cost')->dailyAt('01:15')->withoutOverlapping()->runInBackground();
Schedule::command('system-health:scan-report-consistency')->dailyAt('01:45')->withoutOverlapping()->runInBackground();
Schedule::job(new RunFinancialConsistencyAuditJob())->dailyAt('03:10')->withoutOverlapping();
Schedule::command('system-health:notify-open-critical')->everyThirtyMinutes()->withoutOverlapping()->runInBackground();
