<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class SalesHistoryController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json([
                'message' => 'Tenant tidak ditemukan.',
            ], 400);
        }
        $range = $request->query('preset', $request->query('range', 'today'));
        $from = $request->query('start_date', $request->query('from'));
        $to = $request->query('end_date', $request->query('to'));

        [$fromAt, $toAt, $rangeMeta] = $this->resolveRange($range, $from, $to);

        $query = Sale::query()
            ->leftJoin('branches', function ($join) {
                $join->on('sales.branch_id', '=', 'branches.id')
                    ->on('sales.tenant_id', '=', 'branches.tenant_id');
            })
            ->leftJoin('users', function ($join) {
                $join->on('sales.created_by', '=', 'users.id')
                    ->on('sales.tenant_id', '=', 'users.tenant_id');
            })
            ->select([
                'sales.id',
                'sales.branch_id',
                'sales.customer_id',
                'sales.total',
                'sales.status',
                'sales.created_at',
                'sales.created_by',
                'branches.name as branch_name',
                'users.name as cashier_name',
            ])
            ->where('sales.tenant_id', $tenantId)
            ->where('sales.created_at', '>=', $fromAt)
            ->where('sales.created_at', '<', $toAt)
            ->orderByDesc('sales.created_at');

        if ($request->filled('branch_id')) {
            $query->where('sales.branch_id', $request->query('branch_id'));
        }

        if ($request->filled('cashier_id')) {
            $query->where('sales.created_by', $request->query('cashier_id'));
        }

        if ($request->filled('status') && $request->query('status') !== 'all') {
            $query->where('sales.status', $request->query('status'));
        } elseif (! $request->filled('status')) {
            $query->where('sales.status', 'completed');
        }

        $sales = $query->limit(200)->get();
        $saleIds = $sales->pluck('id')->all();

        $itemMap = [];
        if ($saleIds) {
            $items = SaleItem::query()
                ->where('tenant_id', $tenantId)
                ->select('sale_id')
                ->selectRaw('sum(qty) as qty_total')
                ->selectRaw('count(*) as line_count')
                ->whereIn('sale_id', $saleIds)
                ->groupBy('sale_id')
                ->get();

            foreach ($items as $item) {
                $itemMap[$item->sale_id] = [
                    'qty_total' => (float) $item->qty_total,
                    'line_count' => (int) $item->line_count,
                ];
            }
        }

        $paymentMap = [];
        if ($saleIds) {
            $payments = Payment::query()
                ->where('tenant_id', $tenantId)
                ->whereIn('sale_id', $saleIds)
                ->get(['sale_id', 'method', 'amount']);

            foreach ($payments as $payment) {
                $list = $paymentMap[$payment->sale_id] ?? [];
                $list[] = [
                    'method' => $payment->method,
                    'amount' => (float) $payment->amount,
                ];
                $paymentMap[$payment->sale_id] = $list;
            }
        }

        $rows = $sales->map(function ($sale) use ($itemMap, $paymentMap) {
            $items = $itemMap[$sale->id] ?? ['qty_total' => 0, 'line_count' => 0];
            $payments = $paymentMap[$sale->id] ?? [];

            return [
                'id' => $sale->id,
                'branch_id' => $sale->branch_id,
                'branch_name' => $sale->branch_name,
                'cashier_id' => $sale->created_by,
                'cashier_name' => $sale->cashier_name,
                'total' => (float) $sale->total,
                'status' => $sale->status,
                'created_at' => $sale->created_at,
                'item_count' => $items['line_count'],
                'qty_total' => $items['qty_total'],
                'payments' => $payments,
            ];
        });

        return response()->json([
            'range' => $rangeMeta,
            'sales' => $rows,
        ]);
    }

    private function resolveRange(string $range, ?string $from, ?string $to): array
    {
        $timezone = 'Asia/Jakarta';
        $today = CarbonImmutable::now($timezone)->startOfDay();

        [$startLocal, $endLocal, $label] = match ($range) {
            'today' => [$today, $today, 'Hari Ini'],
            'yesterday' => [$today->subDay(), $today->subDay(), 'Kemarin'],
            'last30' => [$today->subDays(29), $today, '30 Hari Terakhir'],
            'thisMonth' => [$today->startOfMonth(), $today->endOfMonth()->startOfDay(), 'Bulan Ini'],
            'lastMonth' => [
                $today->subMonthNoOverflow()->startOfMonth(),
                $today->subMonthNoOverflow()->endOfMonth()->startOfDay(),
                'Bulan Lalu',
            ],
            'custom' => $this->resolveCustom($from, $to, $today),
            '7days', 'last7' => [$today->subDays(6), $today, '7 Hari Terakhir'],
            default => [$today, $today, 'Hari Ini'],
        };

        $days = $startLocal->diffInDays($endLocal) + 1;
        $limited = false;
        if ($days > 90) {
            $endLocal = $startLocal->addDays(89);
            $limited = true;
        }

        return [
            $startLocal->setTimezone('UTC'),
            $endLocal->addDay()->startOfDay()->setTimezone('UTC'),
            [
                'preset' => $range,
                'start_date' => $startLocal->toDateString(),
                'end_date' => $endLocal->toDateString(),
                'label' => $label,
                'timezone' => 'Asia/Jakarta',
                'limited' => $limited,
                'limit_days' => 90,
            ],
        ];
    }

    private function resolveCustom(?string $from, ?string $to, CarbonImmutable $fallback): array
    {
        $timezone = 'Asia/Jakarta';
        $startLocal = CarbonImmutable::parse($from ?? $fallback->toDateString(), $timezone)->startOfDay();
        $endLocal = CarbonImmutable::parse($to ?? $from ?? $fallback->toDateString(), $timezone)->startOfDay();

        if ($endLocal < $startLocal) {
            [$startLocal, $endLocal] = [$endLocal, $startLocal];
        }

        return [$startLocal, $endLocal, 'Periode Kustom'];
    }
}
