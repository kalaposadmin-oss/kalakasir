<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Services\ProductImportService;
use App\Support\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProductImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $path,
        public ?string $tenantId,
        public ?string $userId = null,
        public array $mapping = [],
    ) {
    }

    public function handle(ProductImportService $imports): void
    {
        if (! is_file($this->path)) {
            return;
        }

        if (! $this->tenantId) {
            Log::warning('Product import skipped because tenant is missing.', [
                'path' => $this->path,
                'user_id' => $this->userId,
            ]);

            @unlink($this->path);

            return;
        }

        $tenant = Tenant::query()->find($this->tenantId);
        if (! $tenant) {
            Log::warning('Product import skipped because tenant was not found.', [
                'tenant_id' => $this->tenantId,
                'path' => $this->path,
                'user_id' => $this->userId,
            ]);

            @unlink($this->path);

            return;
        }

        TenantContext::set($tenant);

        try {
            $summary = $imports->importProducts(
                tenantId: (string) $tenant->id,
                path: $this->path,
                mapping: $this->mapping,
                userId: $this->userId,
            );

            Log::info('Product import completed.', [
                'tenant_id' => $tenant->id,
                'user_id' => $this->userId,
                'created' => $summary['created'],
                'updated' => $summary['updated'],
                'skipped' => $summary['skipped'],
                'total_rows' => $summary['total_rows'],
                'errors' => array_slice($summary['errors'], 0, 10),
            ]);
        } finally {
            TenantContext::clear();
            @unlink($this->path);
        }
    }
}
