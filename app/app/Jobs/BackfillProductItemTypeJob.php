<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class BackfillProductItemTypeJob implements ShouldQueue
{
    use Queueable;

    private const CHECKPOINT_TABLE = 'product_item_type_backfill_checkpoints';

    private const NULL_SCOPE = '__NULL__';

    public const LOCK_TTL_SECONDS = 180;

    public function __construct(
        public ?string $tenantId,
        public int $chunkSize = 500,
        public int $maxBatches = 1,
    ) {
    }

    public function handle(): int
    {
        $processedTotal = 0;
        $batchLimit = max(1, $this->maxBatches);
        $chunkSize = max(1, min(2000, $this->chunkSize));

        for ($batch = 0; $batch < $batchLimit; $batch++) {
            $processed = $this->runOneBatch($this->tenantId, $chunkSize);
            $processedTotal += $processed;

            if ($processed === 0) {
                break;
            }
        }

        return $processedTotal;
    }

    public function runOneBatch(?string $tenantId, int $chunkSize = 500): int
    {
        $chunkSize = max(1, min(2000, $chunkSize));
        $scopeKey = $this->scopeKey($tenantId);

        // Ensure checkpoint row exists before lock/query to avoid first-run races.
        $this->ensureCheckpointExists($tenantId);

        $lock = Cache::lock('products:item_type:backfill:'.$scopeKey, self::LOCK_TTL_SECONDS);
        if (! $lock->get()) {
            return 0;
        }

        try {
            return DB::transaction(function () use ($tenantId, $chunkSize): int {
                $checkpoint = $this->checkpointQuery($tenantId)
                    ->lockForUpdate()
                    ->first(['id', 'last_created_at', 'last_id', 'processed_rows']);

                if (! $checkpoint) {
                    $this->ensureCheckpointExists($tenantId);
                    $checkpoint = $this->checkpointQuery($tenantId)
                        ->lockForUpdate()
                        ->first(['id', 'last_created_at', 'last_id', 'processed_rows']);
                }

                if (! $checkpoint) {
                    throw new \RuntimeException('Checkpoint row tidak ditemukan setelah inisialisasi.');
                }

                $query = DB::table('products')
                    ->select(['id', 'created_at'])
                    ->when($tenantId !== null, function (Builder $builder) use ($tenantId): void {
                        $builder->where('tenant_id', $tenantId);
                    })
                    ->when($checkpoint->last_created_at !== null, function (Builder $builder) use ($checkpoint): void {
                        $lastCreatedAt = (string) $checkpoint->last_created_at;
                        $lastId = (string) ($checkpoint->last_id ?? '');

                        $builder->where(function (Builder $cursor) use ($lastCreatedAt, $lastId): void {
                            $cursor->where('created_at', '>', $lastCreatedAt)
                                ->orWhere(function (Builder $tieBreaker) use ($lastCreatedAt, $lastId): void {
                                    $tieBreaker->where('created_at', '=', $lastCreatedAt)
                                        ->where('id', '>', $lastId);
                                });
                        });
                    })
                    ->orderBy('created_at')
                    ->orderBy('id')
                    ->limit($chunkSize);

                $rows = $query->get();

                if ($rows->isEmpty()) {
                    $this->checkpointQuery($tenantId)->update([
                        'status' => 'completed',
                        'updated_at' => now('UTC'),
                    ]);

                    return 0;
                }

                $ids = $rows->pluck('id')->values();

                DB::table('products')
                    ->whereIn('id', $ids)
                    ->update([
                        'item_type' => DB::raw("CASE WHEN product_mode = 'add_on' THEN 'raw_material' WHEN product_mode = 'bom' THEN 'finished_good' ELSE 'finished_good' END"),
                    ]);

                $last = $rows->last();
                $processed = $rows->count();

                $this->checkpointQuery($tenantId)->update([
                    'last_created_at' => $last->created_at,
                    'last_id' => $last->id,
                    'processed_rows' => (int) $checkpoint->processed_rows + $processed,
                    'status' => 'running',
                    'updated_at' => now('UTC'),
                ]);

                return $processed;
            }, 3);
        } finally {
            $lock->release();
        }
    }

    public function ensureCheckpointExists(?string $tenantId): void
    {
        $now = now('UTC');

        DB::table(self::CHECKPOINT_TABLE)->upsert([
            [
                'scope_key' => $this->scopeKey($tenantId),
                'tenant_id' => $tenantId,
                'last_created_at' => null,
                'last_id' => null,
                'processed_rows' => 0,
                'status' => 'pending',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ], ['scope_key'], ['tenant_id', 'updated_at']);
    }

    private function checkpointQuery(?string $tenantId): Builder
    {
        return DB::table(self::CHECKPOINT_TABLE)
            ->where('scope_key', $this->scopeKey($tenantId))
            ->when($tenantId === null, function (Builder $builder): void {
                $builder->whereNull('tenant_id');
            }, function (Builder $builder) use ($tenantId): void {
                $builder->where('tenant_id', $tenantId);
            });
    }

    private function scopeKey(?string $tenantId): string
    {
        return 'tenant:'.($tenantId ?? self::NULL_SCOPE);
    }
}
