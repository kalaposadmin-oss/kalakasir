<?php

namespace App\Console\Commands;

use App\Models\Product;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class FixProductProductionMode extends Command
{
    protected $signature = 'fix:product-production-mode
        {--tenant= : Tenant ID scope}
        {--tenant_id= : Alias for --tenant}
        {--dry-run : Preview changes without writing to the database}
        {--execute : Apply changes}
        {--chunk=200 : Chunk size for scanning products}';

    protected $description = 'Backfill legacy BOM-based finished goods so they use production_mode=batch_production.';

    public function handle(): int
    {
        $tenantId = trim((string) ($this->option('tenant') ?: $this->option('tenant_id')));
        $dryRun = (bool) $this->option('dry-run');
        $execute = (bool) $this->option('execute');

        if ($tenantId === '') {
            $this->error('Wajib isi --tenant=... atau --tenant_id=....');

            return self::FAILURE;
        }

        if ($dryRun && $execute) {
            $this->error('Pilih salah satu: --dry-run atau --execute.');

            return self::FAILURE;
        }

        if (! $dryRun && ! $execute) {
            $dryRun = true;
        }

        $chunkSize = max(50, (int) $this->option('chunk'));
        $now = CarbonImmutable::now('UTC');
        $summary = [
            'scanned_total' => 0,
            'candidate_total' => 0,
            'updated_total' => 0,
            'skipped_total' => 0,
        ];
        $samples = [];
        $backupRows = [];

        $this->info('Scope tenant: '.$tenantId);
        $this->line('mode: '.($dryRun ? 'dry-run' : 'execute'));
        $this->line('chunk_size: '.$chunkSize);

        $query = Product::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('item_type', 'finished_good')
            ->where(function ($builder): void {
                $builder->whereNull('production_mode')
                    ->orWhere('production_mode', '!=', Product::PRODUCTION_MODE_BATCH);
            })
            ->orderBy('id')
            ->select([
                'id',
                'tenant_id',
                'sku',
                'name',
                'item_type',
                'is_active',
                'product_mode',
                'production_mode',
                'track_stock',
                'bom_items',
                'updated_at',
            ]);

        try {
            $query->chunkById($chunkSize, function ($rows) use (&$summary, &$samples, &$backupRows, $dryRun, $execute, $now): void {
                DB::transaction(function () use ($rows, &$summary, &$samples, &$backupRows, $dryRun, $execute, $now): void {
                    foreach ($rows as $row) {
                        $summary['scanned_total']++;

                        if (! $this->hasActiveBom($row->bom_items)) {
                            continue;
                        }

                        $summary['candidate_total']++;

                        if (count($samples) < 10) {
                            $samples[] = $this->sampleRow($row);
                        }

                        if ($dryRun) {
                            continue;
                        }

                        $locked = Product::query()
                            ->where('id', $row->id)
                            ->where('tenant_id', $row->tenant_id)
                            ->lockForUpdate()
                            ->first([
                                'id',
                                'tenant_id',
                                'sku',
                                'name',
                                'item_type',
                                'is_active',
                                'product_mode',
                                'production_mode',
                                'track_stock',
                                'bom_items',
                                'updated_at',
                            ]);

                        if (! $locked || ! $this->shouldBackfill($locked)) {
                            continue;
                        }

                        Product::query()
                            ->where('id', $locked->id)
                            ->where('tenant_id', $locked->tenant_id)
                            ->update([
                                'product_mode' => 'bom',
                                'production_mode' => Product::PRODUCTION_MODE_BATCH,
                                'updated_at' => $now,
                            ]);

                        $summary['updated_total']++;

                        $backupRows[] = [
                            'id' => (string) $locked->id,
                            'tenant_id' => (string) $locked->tenant_id,
                            'sku' => (string) $locked->sku,
                            'name' => (string) $locked->name,
                            'previous_product_mode' => (string) ($locked->product_mode ?? 'default'),
                            'previous_production_mode' => (string) ($locked->production_mode ?? Product::PRODUCTION_MODE_NONE),
                            'next_product_mode' => 'bom',
                            'next_production_mode' => Product::PRODUCTION_MODE_BATCH,
                            'updated_at' => $now->toIso8601String(),
                        ];
                    }
                }, 3);
            }, 'id');
        } catch (\Throwable $exception) {
            Log::error('fix.product-production-mode failed', [
                'tenant_id' => $tenantId,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        $summary['skipped_total'] = $summary['scanned_total'] - $summary['candidate_total'];

        Log::info('fix.product-production-mode summary', [
            'tenant_id' => $tenantId,
            'mode' => $dryRun ? 'dry-run' : 'execute',
            'chunk_size' => $chunkSize,
            'summary' => $summary,
            'samples' => $samples,
        ]);

        $this->line('scanned_total: '.$summary['scanned_total']);
        $this->line('candidate_total: '.$summary['candidate_total']);
        $this->line('updated_total: '.$summary['updated_total']);
        $this->line('skipped_total: '.$summary['skipped_total']);

        if (! empty($samples)) {
            $this->line('sample:');

            foreach ($samples as $index => $sample) {
                $this->line(sprintf(
                    '  %d. %s | sku=%s | product_mode=%s | production_mode=%s | bom_lines=%d',
                    $index + 1,
                    $sample['name'],
                    $sample['sku'],
                    $sample['product_mode'],
                    $sample['production_mode'],
                    $sample['bom_lines'],
                ));
            }
        }

        if ($dryRun) {
            $this->info('Dry run only.');

            return self::SUCCESS;
        }

        $backupFile = null;
        if ($summary['updated_total'] > 0) {
            $backupDir = storage_path('app/backfills/product-production-mode');
            File::ensureDirectoryExists($backupDir);

            $backupFile = $backupDir.'/tenant-'.$tenantId.'-'.$now->format('Ymd_His').'.json';
            File::put($backupFile, json_encode([
                'tenant_id' => $tenantId,
                'generated_at' => $now->toIso8601String(),
                'rows' => $backupRows,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            $this->line('backup_file: '.$backupFile);
        }

        $this->info('Backfill selesai.');

        return self::SUCCESS;
    }

    private function shouldBackfill(Product $product): bool
    {
        return $product->is_active
            && (string) ($product->item_type ?? '') === 'finished_good'
            && (string) ($product->production_mode ?? Product::PRODUCTION_MODE_NONE) !== Product::PRODUCTION_MODE_BATCH
            && $this->hasActiveBom($product->bom_items);
    }

    private function hasActiveBom(mixed $bomItems): bool
    {
        return count($this->decodeBomItems($bomItems)) > 0;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function decodeBomItems(mixed $bomItems): array
    {
        if (is_string($bomItems)) {
            $bomItems = json_decode($bomItems, true);
        }

        if (! is_array($bomItems)) {
            return [];
        }

        return array_values(array_filter($bomItems, static fn ($item) => is_array($item) && ! empty($item['product_id'])));
    }

    /**
     * @return array<string, mixed>
     */
    private function sampleRow(Product $product): array
    {
        return [
            'id' => (string) $product->id,
            'sku' => (string) $product->sku,
            'name' => (string) $product->name,
            'product_mode' => (string) ($product->product_mode ?? 'default'),
            'production_mode' => (string) ($product->production_mode ?? Product::PRODUCTION_MODE_NONE),
            'bom_lines' => count($this->decodeBomItems($product->bom_items)),
        ];
    }
}