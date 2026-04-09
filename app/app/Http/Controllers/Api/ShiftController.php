<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\AccountMapping;
use App\Models\ChartOfAccount;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\User;
use App\Services\TenantDefaultAccountingSetupService;
use App\Services\DailyFinanceControlService;
use App\Support\Decimal;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ShiftController extends Controller
{
    public function openingData(Request $request)
    {
        $tenant = TenantContext::get();
        $tenantId = $tenant?->id;
        $timezone = $tenant?->timezone ?? 'UTC';

        if ($tenantId) {
            app(TenantDefaultAccountingSetupService::class)->ensure($tenantId, $request->user()?->id);
        }

        $branches = Branch::query()
            ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
            ->orderBy('name')
            ->get(['id', 'name']);
        $cashier = $this->resolveShiftActor($request)?->only(['id', 'name']);
        $openCount = Shift::query()
            ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
            ->where('status', 'open')
            ->count();
        $mapping = $tenantId
            ? AccountMapping::query()->where('tenant_id', $tenantId)->first(['cash_account_id'])
            : null;
        $cashDrawer = $mapping?->cash_account_id
            ? ChartOfAccount::query()
                ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
                ->where('id', $mapping->cash_account_id)
                ->first(['id', 'name'])
            : null;

        return response()->json([
            'shift_number' => $openCount + 1,
            'opened_at' => CarbonImmutable::now($timezone)->toIso8601String(),
            'cashier' => $cashier,
            'branches' => $branches,
            'cash_drawer' => $cashDrawer ? [
                'id' => $cashDrawer->id,
                'name' => $cashDrawer->name,
            ] : null,
        ]);
    }

    public function open(Request $request)
    {
        $tenantId = TenantContext::id();

        if ($tenantId) {
            app(TenantDefaultAccountingSetupService::class)->ensure($tenantId, $request->user()?->id);
        }

        $validated = $request->validate([
            'branch_id' => ['required', 'uuid', $tenantId
                ? Rule::exists('branches', 'id')->where('tenant_id', $tenantId)
                : 'exists:branches,id'],
            'opening_cash' => ['nullable', 'numeric', 'min:0'],
        ]);

        $cashier = $this->resolveShiftActor($request);
        if (! $cashier) {
            return response()->json([
                'code' => 'CASHIER_NOT_FOUND',
                'message' => 'Kasir tidak ditemukan. Pastikan user kasir/admin tersedia untuk tenant ini.',
            ], 422);
        }

        $now = CarbonImmutable::now('UTC');

        $shift = Shift::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'branch_id' => $validated['branch_id'],
            'user_id' => $cashier->id,
            'opened_at' => $now,
            'closed_at' => null,
            'opening_cash' => $validated['opening_cash'] ?? 0,
            'closing_cash' => 0,
            'system_cash_total' => 0,
            'difference' => 0,
            'status' => 'open',
        ]);

        return response()->json([
            'shift' => [
                'id' => $shift->id,
                'branch_id' => $shift->branch_id,
                'user_id' => $shift->user_id,
                'opened_at' => $shift->opened_at,
                'status' => $shift->status,
            ],
        ], 201);
    }

    public function summary(Request $request)
    {
        $tenant = TenantContext::get();
        $tenantId = $tenant?->id;
        $timezone = $tenant?->timezone ?? 'UTC';
        $shiftId = $request->query('shift_id');

        $shift = Shift::query()
            ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
            ->where('status', 'open')
            ->when($shiftId, fn ($query) => $query->where('id', $shiftId))
            ->orderByDesc('opened_at')
            ->firstOrFail();

        $cashier = User::query()
            ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
            ->where('id', $shift->user_id)
            ->first(['id', 'name']);

        $saleIds = Sale::query()
            ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
            ->where('shift_id', $shift->id)
            ->where('status', 'completed')
            ->pluck('id');

        $cashTotal = Decimal::money('0');
        if ($saleIds->isNotEmpty()) {
            $cashTotal = Decimal::money(Payment::query()
                ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
                ->whereIn('sale_id', $saleIds)
                ->where('method', 'cash')
                ->sum('amount'));
        }

        return response()->json([
            'shift' => [
                'id' => $shift->id,
                'user_id' => $shift->user_id,
                'branch_id' => $shift->branch_id,
                'opened_at' => $this->formatShiftDateTime($shift->opened_at, $timezone),
                'status' => $shift->status,
                'opening_cash' => (float) $shift->opening_cash,
            ],
            'cashier' => $cashier ? [
                'id' => $cashier->id,
                'name' => $cashier->name,
            ] : null,
            'expected_cash' => Decimal::float($cashTotal, Decimal::MONEY_SCALE),
        ]);
    }

    public function close(Request $request)
    {
        $tenantId = TenantContext::id();

        $validated = $request->validate([
            'shift_id' => ['required', 'uuid', $tenantId
                ? Rule::exists('shifts', 'id')->where('tenant_id', $tenantId)
                : 'exists:shifts,id'],
            'closing_cash' => ['required', 'numeric', 'min:0'],
            'close_note' => ['nullable', 'string', 'max:500'],
        ]);

        $shift = Shift::query()
            ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
            ->where('id', $validated['shift_id'])
            ->where('status', 'open')
            ->firstOrFail();

        $saleIds = Sale::query()
            ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
            ->where('shift_id', $shift->id)
            ->where('status', 'completed')
            ->pluck('id');

        $cashTotal = Decimal::money('0');
        if ($saleIds->isNotEmpty()) {
            $cashTotal = Decimal::money(Payment::query()
                ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
                ->whereIn('sale_id', $saleIds)
                ->where('method', 'cash')
                ->sum('amount'));
        }

        $difference = Decimal::subtract(Decimal::money($validated['closing_cash']), $cashTotal, Decimal::MONEY_SCALE);

        $shift->update([
            'closing_cash' => $validated['closing_cash'],
            'system_cash_total' => Decimal::float($cashTotal, Decimal::MONEY_SCALE),
            'difference' => Decimal::float($difference, Decimal::MONEY_SCALE),
            'close_note' => $validated['close_note'] ?? null,
            'closed_at' => CarbonImmutable::now('UTC'),
            'status' => 'closed',
        ]);

        if ($tenantId) {
            app(DailyFinanceControlService::class)->capture(
                tenantId: $tenantId,
                date: CarbonImmutable::now('UTC')->toDateString(),
            );
        }

        return response()->json([
            'status' => 'closed',
            'difference' => Decimal::float($difference, Decimal::MONEY_SCALE),
        ]);
    }

    public function closeSummary(Request $request)
    {
        $tenant = TenantContext::get();
        $tenantId = $tenant?->id;
        $timezone = $tenant?->timezone ?? 'UTC';
        $shiftId = $request->query('shift_id');

        $shift = Shift::query()
            ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
            ->where('status', 'closed')
            ->when($shiftId, fn ($query) => $query->where('id', $shiftId))
            ->orderByDesc('closed_at')
            ->firstOrFail();

        $cashier = User::query()
            ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
            ->where('id', $shift->user_id)
            ->first(['id', 'name']);
        $branch = Branch::query()
            ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
            ->where('id', $shift->branch_id)
            ->first(['id', 'name']);

        $saleIds = Sale::query()
            ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
            ->where('shift_id', $shift->id)
            ->where('status', 'completed')
            ->pluck('id');

        $salesTotal = Decimal::money('0');
        $salesCount = 0;
        $payments = [];

        if ($saleIds->isNotEmpty()) {
            $salesTotal = Decimal::money(Sale::query()
                ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
                ->whereIn('id', $saleIds)
                ->sum('total'));
            $salesCount = (int) Sale::query()
                ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
                ->whereIn('id', $saleIds)
                ->count();

            $payments = Payment::query()
                ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
                ->whereIn('sale_id', $saleIds)
                ->selectRaw('method, sum(amount) as total')
                ->groupBy('method')
                ->get()
                ->map(fn ($row) => [
                    'method' => $row->method,
                    'total' => Decimal::float(Decimal::money($row->total), Decimal::MONEY_SCALE),
                ])
                ->values()
                ->all();
        }

            $findings = $this->buildCloseFindings(
                difference: (float) $shift->difference,
                closeNote: (string) ($shift->close_note ?? ''),
                salesCount: $salesCount,
                paymentRows: $payments,
            );

        return response()->json([
            'shift' => [
                'id' => $shift->id,
                'opened_at' => $this->formatShiftDateTime($shift->opened_at, $timezone),
                'closed_at' => $this->formatShiftDateTime($shift->closed_at, $timezone),
                'opening_cash' => (float) $shift->opening_cash,
                'closing_cash' => (float) $shift->closing_cash,
                'system_cash_total' => (float) $shift->system_cash_total,
                'difference' => (float) $shift->difference,
                'close_note' => $shift->close_note,
            ],
            'cashier' => $cashier ? [
                'id' => $cashier->id,
                'name' => $cashier->name,
            ] : null,
            'branch' => $branch ? [
                'id' => $branch->id,
                'name' => $branch->name,
            ] : null,
            'sales' => [
                'total' => Decimal::float($salesTotal, Decimal::MONEY_SCALE),
                'count' => $salesCount,
            ],
            'payments' => $payments,
            'findings' => $findings,
        ]);
    }

    public function closedHistory(Request $request)
    {
        $tenant = TenantContext::get();
        $tenantId = $tenant?->id;
        $timezone = $tenant?->timezone ?? 'UTC';

        $limit = (int) $request->query('limit', 20);
        $limit = max(1, min($limit, 100));

        $shifts = Shift::query()
            ->leftJoin('users', function ($join) {
                $join->on('users.id', '=', 'shifts.user_id')
                    ->on('users.tenant_id', '=', 'shifts.tenant_id');
            })
            ->leftJoin('branches', function ($join) {
                $join->on('branches.id', '=', 'shifts.branch_id')
                    ->on('branches.tenant_id', '=', 'shifts.tenant_id');
            })
            ->when($tenantId, fn ($query) => $query->where('shifts.tenant_id', $tenantId))
            ->where('shifts.status', 'closed')
            ->orderByDesc('shifts.closed_at')
            ->limit($limit)
            ->get([
                'shifts.id',
                'shifts.user_id',
                'shifts.branch_id',
                'shifts.opened_at',
                'shifts.closed_at',
                'users.name as cashier_name',
                'branches.name as branch_name',
            ]);

        $shiftIds = $shifts->pluck('id');

        $salesByShift = Sale::query()
            ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
            ->whereIn('shift_id', $shiftIds)
            ->where('status', 'completed')
            ->selectRaw('shift_id, count(*) as sales_count, sum(total) as sales_total')
            ->groupBy('shift_id')
            ->get()
            ->keyBy('shift_id');

        $rows = $shifts->map(function ($shift) use ($salesByShift, $timezone) {
            $sales = $salesByShift->get($shift->id);

            return [
                'id' => $shift->id,
                'cashier' => $shift->cashier_name,
                'branch' => $shift->branch_name,
                'opened_at' => $this->formatShiftDateTime($shift->opened_at, $timezone),
                'closed_at' => $this->formatShiftDateTime($shift->closed_at, $timezone),
                'sales_total' => (float) ($sales->sales_total ?? 0),
                'sales_count' => (int) ($sales->sales_count ?? 0),
            ];
        })->values();

        return response()->json([
            'shifts' => $rows,
        ]);
    }

    private function buildCloseFindings(float $difference, string $closeNote, int $salesCount, array $paymentRows): array
    {
        $findings = [];

        if ($salesCount === 0) {
            $findings[] = [
                'severity' => 'warn',
                'code' => 'NO_SALES',
                'message' => 'Tidak ada transaksi completed pada shift ini.',
            ];
        }

        if (abs($difference) > 0) {
            $findings[] = [
                'severity' => $difference < 0 ? 'high' : 'warn',
                'code' => 'CASH_DIFFERENCE',
                'message' => $difference < 0
                    ? 'Kas aktual lebih kecil dari ekspektasi sistem.'
                    : 'Kas aktual lebih besar dari ekspektasi sistem.',
                'amount' => abs($difference),
            ];
        }

        if (abs($difference) > 0 && trim($closeNote) === '') {
            $findings[] = [
                'severity' => 'warn',
                'code' => 'DIFFERENCE_NOTE_MISSING',
                'message' => 'Selisih kas terjadi tanpa catatan penutup shift.',
            ];
        }

        $cashPayment = collect($paymentRows)->firstWhere('method', 'cash');
        if ($cashPayment && Decimal::compare(Decimal::money($cashPayment['total']), '0') > 0) {
            $findings[] = [
                'severity' => 'info',
                'code' => 'CASH_PAYMENT_PRESENT',
                'message' => 'Terdapat transaksi tunai pada shift ini, pastikan setoran sesuai.',
            ];
        }

        if (empty($findings)) {
            $findings[] = [
                'severity' => 'ok',
                'code' => 'NO_ISSUE',
                'message' => 'Tidak ada temuan kritis. Tutup shift berjalan normal.',
            ];
        }

        return $findings;
    }

    private function formatShiftDateTime($value, string $timezone): ?string
    {
        if (! $value) {
            return null;
        }

        return CarbonImmutable::parse($value)->setTimezone($timezone)->toIso8601String();
    }

    private function resolveShiftActor(Request $request): ?User
    {
        return $request->user();
    }
}
