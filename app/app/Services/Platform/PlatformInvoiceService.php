<?php

namespace App\Services\Platform;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class PlatformInvoiceService
{
    /**
     * @param array<string, mixed> $filters
     * @return array{invoices: array<int, mixed>, total: int}
     */
    public function list(array $filters, int $page, int $perPage): array
    {
        $query = DB::table('invoices as i')
            ->leftJoin('tenants as t', 't.id', '=', 'i.tenant_id');

        if (! empty($filters['tenant_id'])) {
            $query->where('i.tenant_id', $filters['tenant_id']);
        }

        if (! empty($filters['status'])) {
            $status = (string) $filters['status'];
            if ($status === 'open') {
                $query->whereIn('i.status', ['unpaid', 'open']);
            } elseif ($status === 'overdue') {
                $now = CarbonImmutable::now('UTC');
                $query->whereNotIn('i.status', ['paid', 'void'])
                    ->whereNotNull('i.due_at')
                    ->where('i.due_at', '<', $now);
            } else {
                $query->where('i.status', $status);
            }
        }

        if (! empty($filters['due_from'])) {
            $query->where('i.due_at', '>=', $filters['due_from']);
        }

        if (! empty($filters['due_to'])) {
            $query->where('i.due_at', '<=', $filters['due_to']);
        }

        if (! empty($filters['q'])) {
            $needle = trim((string) $filters['q']);
            $query->where(function ($inner) use ($needle): void {
                $inner->where('i.number', 'like', '%'.$needle.'%')
                    ->orWhere('i.id', 'like', '%'.$needle.'%')
                    ->orWhere('t.name', 'like', '%'.$needle.'%');
            });
        }

        $total = (int) $query->count();

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $sortColumn = match ($sortBy) {
            'due_at' => 'i.due_at',
            'amount' => 'i.amount_due',
            'status' => 'i.status',
            default => 'i.created_at',
        };

        $rows = $query
            ->orderBy($sortColumn, $sortDir === 'asc' ? 'asc' : 'desc')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get([
                'i.id',
                'i.tenant_id',
                't.name as tenant_name',
                'i.number',
                'i.status',
                'i.amount_due',
                'i.amount_paid',
                'i.due_at',
                'i.created_at',
            ]);

        $invoices = $rows->map(function ($row) {
            $dueAt = null;
            if (! empty($row->due_at)) {
                $dueAt = CarbonImmutable::parse($row->due_at, 'UTC')->toIso8601String();
            }

            $createdAt = null;
            if (! empty($row->created_at)) {
                $createdAt = CarbonImmutable::parse($row->created_at, 'UTC')->toIso8601String();
            }

            return [
                'invoice_id' => $row->id,
                'tenant_id' => $row->tenant_id,
                'tenant_name' => $row->tenant_name,
                'number' => $row->number,
                'status' => $this->normalizeInvoiceStatus($row->status, $row->due_at),
                'amount' => (float) $row->amount_due,
                'amount_paid' => (float) $row->amount_paid,
                'due_at' => $dueAt,
                'created_at' => $createdAt,
            ];
        })->all();

        return [
            'invoices' => $invoices,
            'total' => $total,
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function detail(string $invoiceId, array $filters): array
    {
        $invoice = DB::table('invoices')
            ->where('id', $invoiceId)
            ->first();

        if (! $invoice) {
            return [
                'invoice' => null,
                'items' => [],
            ];
        }

        $items = DB::table('invoice_items')
            ->where('invoice_id', $invoiceId)
            ->orderBy('id')
            ->get([
                'id',
                'description',
                'qty',
                'unit_price',
                'total_price',
            ])
            ->map(fn ($row) => (array) $row)
            ->all();

        $issuedAt = null;
        if (! empty($invoice->issued_at)) {
            $issuedAt = CarbonImmutable::parse($invoice->issued_at, 'UTC')->toIso8601String();
        }

        $dueAt = null;
        if (! empty($invoice->due_at)) {
            $dueAt = CarbonImmutable::parse($invoice->due_at, 'UTC')->toIso8601String();
        }

        $paidAt = null;
        if (! empty($invoice->paid_at)) {
            $paidAt = CarbonImmutable::parse($invoice->paid_at, 'UTC')->toIso8601String();
        }

        $createdAt = null;
        if (! empty($invoice->created_at)) {
            $createdAt = CarbonImmutable::parse($invoice->created_at, 'UTC')->toIso8601String();
        }

        return [
            'invoice' => [
                'invoice_id' => $invoice->id,
                'tenant_id' => $invoice->tenant_id,
                'number' => $invoice->number,
                'status' => $this->normalizeInvoiceStatus($invoice->status, $invoice->due_at),
                'amount' => (float) $invoice->amount_due,
                'amount_paid' => (float) $invoice->amount_paid,
                'issued_at' => $issuedAt,
                'due_at' => $dueAt,
                'paid_at' => $paidAt,
                'created_at' => $createdAt,
            ],
            'items' => $items,
        ];
    }

    private function normalizeInvoiceStatus(?string $status, ?string $dueAt): string
    {
        $normalized = strtolower((string) $status);
        if ($normalized === 'paid') {
            return 'paid';
        }

        if ($normalized === 'void') {
            return 'void';
        }

        if ($dueAt && CarbonImmutable::parse($dueAt, 'UTC')->isPast()) {
            return 'overdue';
        }

        return 'open';
    }
}
