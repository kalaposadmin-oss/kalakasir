<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ValuationComparisonExportService
{
    public function export(
        string $tenantId,
        ?string $fromDate,
        ?string $toDate,
        ?string $skuPrefix,
        int $limit
    ): array {
        $limit = max(1, $limit);
        $from = $fromDate ? CarbonImmutable::parse($fromDate)->startOfDay() : null;
        $to = $toDate ? CarbonImmutable::parse($toDate)->endOfDay() : null;

        $service = app(InventoryValuationService::class);
        $state = app(ValuationRebuildStateService::class);

        if ($state->isLocked($tenantId)) {
            throw new RuntimeException('Rebuild lock masih aktif.');
        }

        $originalMethod = DB::table('tenants')
            ->where('id', $tenantId)
            ->value('inventory_valuation_method') ?? 'average';

        $state->lock($tenantId, 3600);

        try {
            $average = $this->collectByMode($tenantId, 'average', $from, $to, $skuPrefix, $limit, $service);
            $fifo = $this->collectByMode($tenantId, 'fifo', $from, $to, $skuPrefix, $limit, $service);

            $skus = array_values(array_unique(array_merge(array_keys($average), array_keys($fifo))));
            sort($skus);

            if (empty($skus)) {
                throw new RuntimeException('Tidak ada data sale_items sesuai filter untuk dibandingkan.');
            }

            $timestamp = CarbonImmutable::now('UTC')->format('Y-m-d_His');
            $dir = storage_path('app/reports');
            if (! is_dir($dir)) {
                mkdir($dir, 0777, true);
            }

            $csvName = 'valuation-comparison-'.$tenantId.'-'.$timestamp.'.csv';
            $mdName = 'valuation-comparison-'.$tenantId.'-'.$timestamp.'.md';
            $csvPath = $dir.DIRECTORY_SEPARATOR.$csvName;
            $mdPath = $dir.DIRECTORY_SEPARATOR.$mdName;

            $csv = "sku,hpp_average,hpp_fifo,delta\n";
            $mdLines = [
                '# Valuation Comparison Report',
                '',
                'Date: '.CarbonImmutable::now('UTC')->toDateString(),
                'Tenant: '.$tenantId,
                'Filter from: '.($from?->toDateString() ?? '-'),
                'Filter to: '.($to?->toDateString() ?? '-'),
                'SKU prefix: '.($skuPrefix ?: '-'),
                'Limit: '.$limit,
                '',
                '| SKU | HPP Average | HPP FIFO | Delta |',
                '|---|---:|---:|---:|',
            ];

            foreach ($skus as $sku) {
                $avg = (float) ($average[$sku] ?? 0);
                $ff = (float) ($fifo[$sku] ?? 0);
                $delta = $avg - $ff;

                $csv .= $sku.','.$avg.','.$ff.','.$delta."\n";
                $mdLines[] = '| '.$sku.' | '.$avg.' | '.$ff.' | '.$delta.' |';
            }

            file_put_contents($csvPath, $csv);
            file_put_contents($mdPath, implode(PHP_EOL, $mdLines).PHP_EOL);

            $state->unlock($tenantId, true, 'valuation export comparison success');

            return [
                'tenant_id' => $tenantId,
                'csv_name' => $csvName,
                'md_name' => $mdName,
                'csv_path' => $csvPath,
                'md_path' => $mdPath,
                'count' => count($skus),
            ];
        } catch (\Throwable $exception) {
            $state->unlock($tenantId, false, $exception->getMessage());
            throw $exception;
        } finally {
            DB::table('tenants')
                ->where('id', $tenantId)
                ->update(['inventory_valuation_method' => $originalMethod]);

            $service->replayFromLedger($tenantId);
        }
    }

    private function collectByMode(
        string $tenantId,
        string $mode,
        ?CarbonImmutable $from,
        ?CarbonImmutable $to,
        ?string $skuPrefix,
        int $limit,
        InventoryValuationService $service
    ): array {
        DB::table('tenants')
            ->where('id', $tenantId)
            ->update(['inventory_valuation_method' => $mode]);

        $service->replayFromLedger($tenantId);

        $query = DB::table('sale_items as si')
            ->join('sales as s', function ($join) {
                $join->on('si.sale_id', '=', 's.id')
                    ->on('si.tenant_id', '=', 's.tenant_id');
            })
            ->join('products as p', function ($join) {
                $join->on('si.product_id', '=', 'p.id')
                    ->on('si.tenant_id', '=', 'p.tenant_id');
            })
            ->where('si.tenant_id', $tenantId)
            ->where('s.status', 'completed');

        if ($from) {
            $query->where('s.created_at', '>=', $from->toDateTimeString());
        }

        if ($to) {
            $query->where('s.created_at', '<=', $to->toDateTimeString());
        }

        if ($skuPrefix) {
            $query->where('p.sku', 'like', $skuPrefix.'%');
        }

        $rows = $query
            ->select('p.sku', 'si.hpp_snapshot', 's.created_at')
            ->orderBy('p.sku')
            ->orderByDesc('s.created_at')
            ->limit($limit * 20)
            ->get();

        $result = [];
        foreach ($rows as $row) {
            if (! array_key_exists($row->sku, $result)) {
                $result[$row->sku] = (float) $row->hpp_snapshot;
            }

            if (count($result) >= $limit) {
                break;
            }
        }

        ksort($result);

        return $result;
    }

    public function pruneExpiredReports(int $retentionDays): int
    {
        $retentionDays = max(1, $retentionDays);
        $threshold = CarbonImmutable::now('UTC')->subDays($retentionDays)->getTimestamp();
        $dir = storage_path('app/reports');

        if (! is_dir($dir)) {
            return 0;
        }

        $deleted = 0;
        foreach (scandir($dir) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }

            if (! str_starts_with($name, 'valuation-comparison-')) {
                continue;
            }

            if (! str_ends_with($name, '.csv') && ! str_ends_with($name, '.md')) {
                continue;
            }

            $path = $dir.DIRECTORY_SEPARATOR.$name;
            $mtime = filemtime($path) ?: time();
            if ($mtime < $threshold && is_file($path)) {
                @unlink($path);
                $deleted++;
            }
        }

        return $deleted;
    }
}
