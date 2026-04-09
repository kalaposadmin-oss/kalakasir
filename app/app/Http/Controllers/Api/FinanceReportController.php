<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\SubscriptionService;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\TenantContext;

class FinanceReportController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = TenantContext::id();
        $tenant = TenantContext::get();
        if (! $tenantId) {
            return response()->json([
                'message' => 'Tenant tidak ditemukan.',
            ], 400);
        }
        $preset = $request->query('preset', 'thisMonth');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $month = $request->query('month');

        if ($month && ! $startDate && ! $endDate) {
            $startDate = $month . '-01';
            $preset = 'custom';
        }

        [$startUtc, $endExclusiveUtc, $rangeMeta] = $this->resolveRange($preset, $startDate, $endDate);

        $sales = Sale::query()
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', $startUtc)
            ->where('created_at', '<', $endExclusiveUtc)
            ->where('status', 'completed')
            ->get(['id', 'total']);

        $saleIds = $sales->pluck('id')->all();
        $grossRevenue = Decimal::money($sales->sum('total'));

        $refunds = DB::table('refunds')
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->where('created_at', '>=', $startUtc)
            ->where('created_at', '<', $endExclusiveUtc)
            ->selectRaw('COALESCE(SUM(total), 0) as total')
            ->selectRaw('COALESCE(SUM(refunded_cost_total), 0) as refunded_cost_total')
            ->first();

        $refundRevenue = Decimal::money($refunds?->total ?? 0);
        $refundedCostTotal = Decimal::money($refunds?->refunded_cost_total ?? 0);
        $revenue = Decimal::subtract($grossRevenue, $refundRevenue, Decimal::MONEY_SCALE);

        $cogs = Decimal::money(0);
        if ($saleIds) {
            $cogs = Decimal::money(SaleItem::query()
                ->where('tenant_id', $tenantId)
                ->whereIn('sale_id', $saleIds)
                ->selectRaw('sum(qty * hpp_snapshot) as total')
                ->value('total'));
        }

        $netCogs = Decimal::subtract($cogs, $refundedCostTotal, Decimal::MONEY_SCALE);

        $expenses = [
            ['label' => 'HPP (Harga Pokok Penjualan)', 'amount' => Decimal::float($netCogs, Decimal::MONEY_SCALE)],
            ['label' => 'Gaji Karyawan', 'amount' => 0],
            ['label' => 'Sewa dan Utilitas', 'amount' => 0],
            ['label' => 'Pemasaran', 'amount' => 0],
            ['label' => 'Operasional Lainnya', 'amount' => 0],
        ];

        $totalExpenses = collect($expenses)
            ->reduce(fn (string $carry, array $row) => Decimal::add($carry, $row['amount'], Decimal::MONEY_SCALE), Decimal::money(0));

        $netProfit = Decimal::subtract($revenue, $totalExpenses, Decimal::MONEY_SCALE);
        $subscription = app(SubscriptionService::class);
        $canViewBalanceSheet = $tenant ? $subscription->canAccessFeature($tenant, 'finance.balance_sheet') : false;

        $payload = [
            'period' => $rangeMeta,
            'summary' => [
                'net_profit' => Decimal::float($netProfit, Decimal::MONEY_SCALE),
                'revenue' => Decimal::float($revenue, Decimal::MONEY_SCALE),
                'expenses' => Decimal::float($totalExpenses, Decimal::MONEY_SCALE),
                'tax' => 0,
            ],
            'revenue_breakdown' => [
                ['label' => 'Penjualan POS', 'amount' => Decimal::float($grossRevenue, Decimal::MONEY_SCALE)],
                ['label' => 'Retur Penjualan', 'amount' => Decimal::float(Decimal::subtract(0, $refundRevenue, Decimal::MONEY_SCALE), Decimal::MONEY_SCALE)],
                ['label' => 'Pendapatan Lainnya', 'amount' => 0],
            ],
            'expense_breakdown' => $expenses,
        ];

        if ($canViewBalanceSheet) {
            $payload['balance_sheet'] = [
                'assets' => [
                    ['label' => 'Kas dan Bank', 'amount' => $revenue],
                ],
                'liabilities' => [
                    ['label' => 'Hutang Usaha', 'amount' => 0],
                ],
                'equity' => [
                    ['label' => 'Modal Bersih', 'amount' => Decimal::float($netProfit, Decimal::MONEY_SCALE)],
                ],
            ];
        }

        return response()->json($payload);
    }

    private function resolveRange(string $preset, ?string $startDate, ?string $endDate): array
    {
        $timezone = 'Asia/Jakarta';
        $today = CarbonImmutable::now($timezone)->startOfDay();

        [$startLocal, $endLocal, $label] = match ($preset) {
            'today' => [$today, $today, 'Hari Ini'],
            'yesterday' => [$today->subDay(), $today->subDay(), 'Kemarin'],
            'last30', '30days' => [$today->subDays(29), $today, '30 Hari Terakhir'],
            'thisMonth' => [$today->startOfMonth(), $today->endOfMonth()->startOfDay(), 'Bulan Ini'],
            'lastMonth' => [
                $today->subMonthNoOverflow()->startOfMonth(),
                $today->subMonthNoOverflow()->endOfMonth()->startOfDay(),
                'Bulan Lalu',
            ],
            'custom' => $this->resolveCustomRange($startDate, $endDate, $today),
            'last7', '7days' => [$today->subDays(6), $today, '7 Hari Terakhir'],
            default => [$today->startOfMonth(), $today->endOfMonth()->startOfDay(), 'Bulan Ini'],
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
                'preset' => $preset,
                'start_date' => $startLocal->toDateString(),
                'end_date' => $endLocal->toDateString(),
                'label' => $label,
                'timezone' => 'Asia/Jakarta',
                'limited' => $limited,
                'limit_days' => 90,
            ],
        ];
    }

    private function resolveCustomRange(?string $startDate, ?string $endDate, CarbonImmutable $fallback): array
    {
        $timezone = 'Asia/Jakarta';
        $startLocal = CarbonImmutable::parse($startDate ?? $fallback->toDateString(), $timezone)->startOfDay();
        $endLocal = CarbonImmutable::parse($endDate ?? $startDate ?? $fallback->toDateString(), $timezone)->startOfDay();

        if ($endLocal < $startLocal) {
            [$startLocal, $endLocal] = [$endLocal, $startLocal];
        }

        return [$startLocal, $endLocal, 'Periode Kustom'];
    }
}
