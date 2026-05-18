<?php

namespace App\Exceptions;

use RuntimeException;

class MissingSalesCostAggregateException extends RuntimeException
{
    public function __construct(
        private readonly string $context,
        private readonly array $saleIds,
        private readonly string $reason = 'missing_aggregate',
        private readonly array $summary = []
    ) {
        parent::__construct(match ($this->reason) {
            'missing_header_cogs' => 'COGS aggregated tidak valid untuk '.$context.' karena sales.cogs_total masih kosong. Jalankan sales-cost:validate lalu sales-cost:rebuild sebelum membuka laporan ini.',
            'mismatched_header_cogs' => 'COGS aggregated tidak konsisten untuk '.$context.' karena sales.cogs_total berbeda dari aggregate komponennya. Jalankan sales-cost:validate lalu sales-cost:rebuild sebelum membuka laporan ini.',
            'invalid_sales_cost_state' => 'COGS aggregated belum valid untuk '.$context.'. Jalankan sales-cost:validate lalu sales-cost:rebuild sebelum membuka laporan ini.',
            default => 'COGS aggregated belum lengkap untuk '.$context.'. Jalankan sales-cost:validate lalu sales-cost:rebuild sebelum membuka laporan ini.',
        });
    }

    public function scope(): string
    {
        return $this->context;
    }

    public function saleIds(): array
    {
        return $this->saleIds;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function details(): array
    {
        return [
            'context' => $this->context,
            'reason' => $this->reason,
            'invalid_sale_count' => count($this->saleIds),
            'sample_sale_ids' => $this->saleIds,
            'summary' => $this->summary,
        ];
    }
}