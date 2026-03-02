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
use App\Services\DailyFinanceControlService;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ShiftController extends Controller
{
    public function openingData(Request $request)
    {
        $tenant = TenantContext::get();
        $timezone = $tenant?->timezone ?? 'UTC';

        $branches = Branch::query()->orderBy('name')->get(['id', 'name']);
        $cashier = $this->resolveShiftActor($request)?->only(['id', 'name']);
        $openCount = Shift::query()->where('status', 'open')->count();
        $mapping = $tenant?->id
            ? AccountMapping::query()->where('tenant_id', $tenant->id)->first(['cash_account_id'])
            : null;
        $cashDrawer = $mapping?->cash_account_id
            ? ChartOfAccount::query()->where('id', $mapping->cash_account_id)->first(['id', 'name'])
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

        $validated = $request->validate([
            'branch_id' => ['required', 'uuid'],
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
        $timezone = $tenant?->timezone ?? 'UTC';
        $shiftId = $request->query('shift_id');

        $shift = Shift::query()
            ->where('status', 'open')
            ->when($shiftId, fn ($query) => $query->where('id', $shiftId))
            ->orderByDesc('opened_at')
            ->firstOrFail();

        $cashier = User::query()->where('id', $shift->user_id)->first(['id', 'name']);

        $saleIds = Sale::query()
            ->where('shift_id', $shift->id)
            ->where('status', 'completed')
            ->pluck('id');

        $cashTotal = 0.0;
        if ($saleIds->isNotEmpty()) {
            $cashTotal = (float) Payment::query()
                ->whereIn('sale_id', $saleIds)
                ->where('method', 'cash')
                ->sum('amount');
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
            'expected_cash' => $cashTotal,
        ]);
    }

    public function close(Request $request)
    {
        $tenantId = TenantContext::id();

        $validated = $request->validate([
            'shift_id' => ['required', 'uuid'],
            'closing_cash' => ['required', 'numeric', 'min:0'],
            'close_note' => ['nullable', 'string', 'max:500'],
        ]);

        $shift = Shift::query()
            ->where('id', $validated['shift_id'])
            ->where('status', 'open')
            ->firstOrFail();

        $saleIds = Sale::query()
            ->where('shift_id', $shift->id)
            ->where('status', 'completed')
            ->pluck('id');

        $cashTotal = 0.0;
        if ($saleIds->isNotEmpty()) {
            $cashTotal = (float) Payment::query()
                ->whereIn('sale_id', $saleIds)
                ->where('method', 'cash')
                ->sum('amount');
        }

        $difference = (float) $validated['closing_cash'] - $cashTotal;

        $shift->update([
            'closing_cash' => $validated['closing_cash'],
            'system_cash_total' => $cashTotal,
            'difference' => $difference,
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
            'difference' => $difference,
        ]);
    }

    public function closeSummary(Request $request)
    {
        $tenant = TenantContext::get();
        $timezone = $tenant?->timezone ?? 'UTC';
        $shiftId = $request->query('shift_id');

        $shift = Shift::query()
            ->where('status', 'closed')
            ->when($shiftId, fn ($query) => $query->where('id', $shiftId))
            ->orderByDesc('closed_at')
            ->firstOrFail();

        $cashier = User::query()->where('id', $shift->user_id)->first(['id', 'name']);
        $branch = Branch::query()->where('id', $shift->branch_id)->first(['id', 'name']);

        $saleIds = Sale::query()
            ->where('shift_id', $shift->id)
            ->where('status', 'completed')
            ->pluck('id');

        $salesTotal = 0.0;
        $salesCount = 0;
        $payments = [];

        if ($saleIds->isNotEmpty()) {
            $salesTotal = (float) Sale::query()
                ->whereIn('id', $saleIds)
                ->sum('total');
            $salesCount = (int) Sale::query()
                ->whereIn('id', $saleIds)
                ->count();

            $payments = Payment::query()
                ->whereIn('sale_id', $saleIds)
                ->selectRaw('method, sum(amount) as total')
                ->groupBy('method')
                ->get()
                ->map(fn ($row) => [
                    'method' => $row->method,
                    'total' => (float) $row->total,
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
                'total' => $salesTotal,
                'count' => $salesCount,
            ],
            'payments' => $payments,
            'findings' => $findings,
        ]);
    }

    public function closedHistory(Request $request)
    {
        $tenant = TenantContext::get();
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
        if ($cashPayment && (float) $cashPayment['total'] > 0) {
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
        $actor = $request->user();
        if ($actor) {
            return $actor;
        }

        if (! app()->environment('local')) {
            return null;
        }

        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return null;
        }

        $fallbackUserId = DB::table('users')
            ->join('user_roles', function ($join) {
                $join->on('users.id', '=', 'user_roles.user_id')
                    ->on('users.tenant_id', '=', 'user_roles.tenant_id');
            })
            ->join('roles', function ($join) {
                $join->on('roles.id', '=', 'user_roles.role_id')
                    ->on('roles.tenant_id', '=', 'user_roles.tenant_id');
            })
            ->where('users.tenant_id', $tenantId)
            ->whereIn('roles.name', ['cashier', 'kasir', 'supervisor', 'manager', 'admin', 'administrator'])
            ->orderBy('users.name')
            ->value('users.id');

        if (! $fallbackUserId) {
            $fallback = User::query()
                ->where('tenant_id', $tenantId)
                ->orderBy('name')
                ->first(['id', 'name']);

            if ($fallback) {
                return $fallback;
            }

            return $this->provisionLocalFallbackActor($tenantId);
        }

        return User::query()->where('id', $fallbackUserId)->first(['id', 'name']);
    }

    private function provisionLocalFallbackActor(string $tenantId): ?User
    {
        if (! app()->environment('local')) {
            return null;
        }

        $now = CarbonImmutable::now('UTC');
        $userId = (string) Str::uuid();
        $roleId = (string) Str::uuid();

        DB::transaction(function () use ($tenantId, $now, $userId, $roleId): void {
            $roleIdForTenant = DB::table('roles')
                ->where('tenant_id', $tenantId)
                ->where('name', 'cashier')
                ->value('id');

            if (! $roleIdForTenant) {
                DB::table('roles')->insert([
                    'id' => $roleId,
                    'tenant_id' => $tenantId,
                    'name' => 'cashier',
                ]);
                $roleIdForTenant = $roleId;
            }

            $email = 'local-cashier-'.substr(str_replace('-', '', $tenantId), 0, 8).'@local.test';

            DB::table('users')->insert([
                'id' => $userId,
                'tenant_id' => $tenantId,
                'name' => 'Kasir Local',
                'email' => $email,
                'password' => Hash::make(Str::random(32)),
                'failed_login_attempts' => 0,
                'remember_token' => null,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]);

            DB::table('user_roles')->insert([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'role_id' => $roleIdForTenant,
            ]);
        });

        return User::query()->where('id', $userId)->first(['id', 'name']);
    }
}
