<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\MissingSalesCostAggregateException;
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
use App\Services\ShiftLifecycleService;
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
        $shiftLifecycle = app(ShiftLifecycleService::class);

        $shiftLifecycle->autoCloseExpiredOpenShifts($tenant);
        $pendingReconciliation = $shiftLifecycle->pendingReconciliation($tenant, $request->user()?->id);

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
            'pending_reconciliation' => $pendingReconciliation ? [
                'shift_id' => $pendingReconciliation->id,
                'opened_at' => $this->formatShiftDateTime($pendingReconciliation->opened_at, $timezone),
                'closed_at' => $this->formatShiftDateTime($pendingReconciliation->closed_at, $timezone),
                'opening_cash' => (float) $pendingReconciliation->opening_cash,
                'expected_cash' => (float) $pendingReconciliation->system_cash_total,
                'branch_id' => $pendingReconciliation->branch_id,
                'message' => 'Shift kemarin ditutup otomatis karena melewati 00.00. Isi kas akhir fisik dulu sebelum membuka shift baru.',
            ] : null,
        ]);
    }

    public function open(Request $request)
    {
        $tenant = TenantContext::get();
        $tenantId = $tenant?->id;
        $shiftLifecycle = app(ShiftLifecycleService::class);

        $shiftLifecycle->autoCloseExpiredOpenShifts($tenant);

        if ($tenantId) {
            app(TenantDefaultAccountingSetupService::class)->ensure($tenantId, $request->user()?->id);
        }

        $pendingReconciliation = $shiftLifecycle->pendingReconciliation($tenant, $request->user()?->id);
        if ($pendingReconciliation) {
            return response()->json([
                'code' => 'SHIFT_RECONCILIATION_REQUIRED',
                'message' => 'Selesaikan kas akhir shift kemarin terlebih dahulu sebelum membuka shift baru.',
                'pending_shift_id' => $pendingReconciliation->id,
            ], 422);
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
        app(ShiftLifecycleService::class)->autoCloseExpiredOpenShifts($tenant);

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

        $cashComponents = app(ShiftLifecycleService::class)->cashComponentsForShift($tenantId, $shift);

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
            'cash_sales' => Decimal::float($cashComponents['cash_sales'], Decimal::MONEY_SCALE),
            'receivable_cash' => Decimal::float($cashComponents['receivable_cash'], Decimal::MONEY_SCALE),
            'expected_cash' => Decimal::float($cashComponents['expected_cash'], Decimal::MONEY_SCALE),
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

        $cashComponents = app(ShiftLifecycleService::class)->cashComponentsForShift($tenantId, $shift);
        $cashTotal = $cashComponents['expected_cash'];

        $difference = Decimal::subtract(Decimal::money($validated['closing_cash']), $cashTotal, Decimal::MONEY_SCALE);

        $shift->update([
            'closing_cash' => $validated['closing_cash'],
            'system_cash_total' => Decimal::float($cashTotal, Decimal::MONEY_SCALE),
            'difference' => Decimal::float($difference, Decimal::MONEY_SCALE),
            'close_note' => $validated['close_note'] ?? null,
            'closed_at' => CarbonImmutable::now('UTC'),
            'status' => 'closed',
            'closed_by_system' => false,
            'requires_reconciliation' => false,
            'reconciliation_completed_at' => CarbonImmutable::now('UTC'),
            'reconciliation_completed_by' => $request->user()?->id,
        ]);

        if ($tenantId) {
            try {
                app(DailyFinanceControlService::class)->capture(
                    tenantId: $tenantId,
                    date: CarbonImmutable::now('UTC')->toDateString(),
                );
            } catch (MissingSalesCostAggregateException $exception) {
                report($exception);
            }
        }

        return response()->json([
            'status' => 'closed',
            'difference' => Decimal::float($difference, Decimal::MONEY_SCALE),
        ]);
    }

    public function reconcileOvernight(Request $request)
    {
        $tenant = TenantContext::get();
        $tenantId = $tenant?->id;

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
            ->where('status', 'closed')
            ->where('requires_reconciliation', true)
            ->firstOrFail();

        $resolved = app(ShiftLifecycleService::class)->reconcile(
            $shift,
            $validated['closing_cash'],
            $validated['close_note'] ?? null,
            $request->user()?->id,
        );

        return response()->json([
            'status' => 'reconciled',
            'shift' => [
                'id' => $resolved->id,
                'closing_cash' => (float) $resolved->closing_cash,
                'system_cash_total' => (float) $resolved->system_cash_total,
                'difference' => (float) $resolved->difference,
                'requires_reconciliation' => (bool) $resolved->requires_reconciliation,
            ],
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

        $receivableCashTotal = Decimal::money(
            DB::table('receivable_payments')
                ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
                ->where('shift_id', $shift->id)
                ->where('payment_method', 'cash')
                ->sum('amount')
        );

        if (Decimal::compare($receivableCashTotal, '0') > 0) {
            $payments[] = [
                'method' => 'receivable_cash',
                'total' => Decimal::float($receivableCashTotal, Decimal::MONEY_SCALE),
            ];
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
                'closed_by_system' => (bool) ($shift->closed_by_system ?? false),
                'requires_reconciliation' => (bool) ($shift->requires_reconciliation ?? false),
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
        $range = $request->query('preset', $request->query('range', 'last7'));
        $from = $request->query('start_date', $request->query('from'));
        $to = $request->query('end_date', $request->query('to'));

        [$fromAt, $toAt, $rangeMeta] = $this->resolveClosedShiftRange($range, $from, $to, $timezone);

        $limit = (int) $request->query('limit', 20);
        $limit = max(1, min($limit, 100));
        $statusFilter = (string) $request->query('status', 'all');
        $search = trim((string) $request->query('q', ''));

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
            ->where('shifts.closed_at', '>=', $fromAt)
            ->where('shifts.closed_at', '<', $toAt)
            ->when($request->filled('branch_id'), fn ($query) => $query->where('shifts.branch_id', $request->query('branch_id')))
            ->when($request->filled('cashier_id'), fn ($query) => $query->where('shifts.user_id', $request->query('cashier_id')))
            ->when($statusFilter === 'balanced', fn ($query) => $query->where('shifts.difference', '=', 0))
            ->when($statusFilter === 'difference', fn ($query) => $query->where('shifts.difference', '<>', 0))
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.$search.'%';

                $query->where(function ($inner) use ($like) {
                    $inner->where('users.name', 'like', $like)
                        ->orWhere('branches.name', 'like', $like);
                });
            })
            ->orderByDesc('shifts.closed_at')
            ->limit($limit)
            ->get([
                'shifts.id',
                'shifts.user_id',
                'shifts.branch_id',
                'shifts.opened_at',
                'shifts.closed_at',
                'shifts.opening_cash',
                'shifts.closing_cash',
                'shifts.system_cash_total',
                'shifts.difference',
                'shifts.closed_by_system',
                'shifts.requires_reconciliation',
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
                'cashier_id' => $shift->user_id,
                'branch_id' => $shift->branch_id,
                'cashier' => $shift->cashier_name,
                'branch' => $shift->branch_name,
                'opened_at' => $this->formatShiftDateTime($shift->opened_at, $timezone),
                'closed_at' => $this->formatShiftDateTime($shift->closed_at, $timezone),
                'opening_cash' => (float) $shift->opening_cash,
                'closing_cash' => (float) $shift->closing_cash,
                'system_cash_total' => (float) $shift->system_cash_total,
                'difference' => (float) $shift->difference,
                'closed_by_system' => (bool) ($shift->closed_by_system ?? false),
                'requires_reconciliation' => (bool) ($shift->requires_reconciliation ?? false),
                'sales_total' => (float) ($sales->sales_total ?? 0),
                'sales_count' => (int) ($sales->sales_count ?? 0),
            ];
        })->values();

        return response()->json([
            'shifts' => $rows,
            'range' => $rangeMeta,
        ]);
    }

    private function resolveClosedShiftRange(string $range, ?string $from, ?string $to, string $timezone): array
    {
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
            'custom' => $this->resolveClosedShiftCustomRange($from, $to, $today, $timezone),
            '7days', 'last7' => [$today->subDays(6), $today, '7 Hari Terakhir'],
            default => [$today->subDays(6), $today, '7 Hari Terakhir'],
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
                'timezone' => $timezone,
                'limited' => $limited,
                'limit_days' => 90,
            ],
        ];
    }

    private function resolveClosedShiftCustomRange(?string $from, ?string $to, CarbonImmutable $fallback, string $timezone): array
    {
        $startLocal = CarbonImmutable::parse($from ?? $fallback->toDateString(), $timezone)->startOfDay();
        $endLocal = CarbonImmutable::parse($to ?? $from ?? $fallback->toDateString(), $timezone)->startOfDay();

        if ($endLocal < $startLocal) {
            [$startLocal, $endLocal] = [$endLocal, $startLocal];
        }

        return [$startLocal, $endLocal, 'Periode Kustom'];
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
