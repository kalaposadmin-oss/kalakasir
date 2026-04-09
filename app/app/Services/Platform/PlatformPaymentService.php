<?php

namespace App\Services\Platform;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class PlatformPaymentService
{
    /**
     * @param array<string, mixed> $filters
     * @return array{payments: array<int, mixed>, total: int}
     */
    public function list(array $filters, int $page, int $perPage): array
    {
        $query = DB::table('subscription_payments as sp')
            ->leftJoin('tenants as t', 't.id', '=', 'sp.tenant_id')
            ->leftJoin('invoices as i', 'i.id', '=', 'sp.invoice_id');

        if (! empty($filters['tenant_id'])) {
            $query->where('sp.tenant_id', $filters['tenant_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('sp.status', $filters['status']);
        }

        if (! empty($filters['paid_from'])) {
            $query->where('sp.paid_at', '>=', $filters['paid_from']);
        }

        if (! empty($filters['paid_to'])) {
            $query->where('sp.paid_at', '<=', $filters['paid_to']);
        }

        if (! empty($filters['q'])) {
            $needle = trim((string) $filters['q']);
            $query->where(function ($inner) use ($needle): void {
                $inner->where('sp.reference', 'like', '%'.$needle.'%')
                    ->orWhere('sp.provider', 'like', '%'.$needle.'%')
                    ->orWhere('i.number', 'like', '%'.$needle.'%')
                    ->orWhere('t.name', 'like', '%'.$needle.'%');
            });
        }

        $total = (int) $query->count();

        $sortBy = $filters['sort_by'] ?? 'paid_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $sortColumn = match ($sortBy) {
            'amount' => 'sp.amount',
            'status' => 'sp.status',
            'created_at' => 'sp.created_at',
            default => 'sp.paid_at',
        };

        $rows = $query
            ->orderBy($sortColumn, $sortDir === 'asc' ? 'asc' : 'desc')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get([
                'sp.id',
                'sp.tenant_id',
                't.name as tenant_name',
                'sp.invoice_id',
                'i.number as invoice_number',
                'sp.provider',
                'sp.reference',
                'sp.status',
                'sp.amount',
                'sp.paid_at',
                'sp.created_at',
            ]);

        $payments = $rows->map(function ($row) {
            $paidAt = null;
            if (! empty($row->paid_at)) {
                $paidAt = CarbonImmutable::parse($row->paid_at, 'UTC')->toIso8601String();
            }

            $createdAt = null;
            if (! empty($row->created_at)) {
                $createdAt = CarbonImmutable::parse($row->created_at, 'UTC')->toIso8601String();
            }

            return [
                'payment_id' => $row->id,
                'tenant_id' => $row->tenant_id,
                'tenant_name' => $row->tenant_name,
                'invoice_id' => $row->invoice_id,
                'invoice_number' => $row->invoice_number,
                'provider' => $row->provider,
                'reference' => $row->reference,
                'status' => $row->status,
                'amount' => (float) $row->amount,
                'paid_at' => $paidAt,
                'created_at' => $createdAt,
            ];
        })->all();

        return [
            'payments' => $payments,
            'total' => $total,
        ];
    }
}
