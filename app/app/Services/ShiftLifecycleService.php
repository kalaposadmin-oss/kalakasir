<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\Tenant;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ShiftLifecycleService
{
    public function autoCloseExpiredOpenShifts(?Tenant $tenant): void
    {
        if (! $tenant) {
            return;
        }

        $timezone = $tenant->timezone ?: 'UTC';
        $nowUtc = CarbonImmutable::now('UTC');
        $todayLocal = $nowUtc->setTimezone($timezone)->startOfDay();

        Shift::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'open')
            ->orderBy('opened_at')
            ->get()
            ->each(function (Shift $shift) use ($tenant, $timezone, $todayLocal): void {
                $openedLocal = CarbonImmutable::parse($shift->opened_at, 'UTC')->setTimezone($timezone);
                if (! $openedLocal->lt($todayLocal)) {
                    return;
                }

                $expectedCash = $this->expectedCashForShift($tenant->id, $shift);
                $autoClosedAtUtc = $openedLocal->addDay()->startOfDay()->setTimezone('UTC');

                $shift->update([
                    'closing_cash' => 0,
                    'system_cash_total' => Decimal::float($expectedCash, Decimal::MONEY_SCALE),
                    'difference' => Decimal::float(
                        Decimal::subtract(Decimal::money('0'), $expectedCash, Decimal::MONEY_SCALE),
                        Decimal::MONEY_SCALE
                    ),
                    'close_note' => 'Ditutup otomatis oleh sistem karena shift melewati 00.00 tanpa penutupan manual.',
                    'closed_at' => $autoClosedAtUtc,
                    'status' => 'closed',
                    'closed_by_system' => true,
                    'requires_reconciliation' => true,
                    'reconciliation_completed_at' => null,
                    'reconciliation_completed_by' => null,
                ]);
            });
    }

    public function pendingReconciliation(?Tenant $tenant, ?string $userId = null): ?Shift
    {
        if (! $tenant) {
            return null;
        }

        return Shift::query()
            ->where('tenant_id', $tenant->id)
            ->where('requires_reconciliation', true)
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->orderByDesc('closed_at')
            ->first();
    }

    public function reconcile(Shift $shift, float|int|string $closingCash, ?string $note, ?string $actorId): Shift
    {
        $expectedCash = Decimal::money($shift->system_cash_total);
        $closingCashValue = Decimal::money($closingCash);
        $difference = Decimal::subtract($closingCashValue, $expectedCash, Decimal::MONEY_SCALE);

        $shift->update([
            'closing_cash' => Decimal::float($closingCashValue, Decimal::MONEY_SCALE),
            'difference' => Decimal::float($difference, Decimal::MONEY_SCALE),
            'close_note' => $this->mergeReconciliationNote((string) ($shift->close_note ?? ''), $note),
            'requires_reconciliation' => false,
            'reconciliation_completed_at' => CarbonImmutable::now('UTC'),
            'reconciliation_completed_by' => $actorId,
        ]);

        return $shift->fresh();
    }

    public function expectedCashForShift(?string $tenantId, Shift|string $shift): string
    {
        return $this->cashComponentsForShift($tenantId, $shift)['expected_cash'];
    }

    /**
     * @return array{opening_cash: string, cash_sales: string, receivable_cash: string, cash_movements: string, expected_cash: string}
     */
    public function cashComponentsForShift(?string $tenantId, Shift|string $shift): array
    {
        $shiftId = $shift instanceof Shift ? (string) $shift->id : (string) $shift;
        $effectiveTenantId = $tenantId ?: ($shift instanceof Shift ? (string) $shift->tenant_id : null);
        $openingCash = Decimal::money($shift instanceof Shift
            ? ($shift->opening_cash ?? 0)
            : (Shift::query()
                ->when($effectiveTenantId, fn ($query) => $query->where('tenant_id', $effectiveTenantId))
                ->where('id', $shiftId)
                ->value('opening_cash') ?? 0)
        );

        $saleIds = Sale::query()
            ->when($effectiveTenantId, fn ($query) => $query->where('tenant_id', $effectiveTenantId))
            ->where('shift_id', $shiftId)
            ->where('status', 'completed')
            ->pluck('id');

        $cashSalesTotal = Decimal::money('0');
        if ($saleIds->isNotEmpty()) {
            $cashSalesTotal = Decimal::money(Payment::query()
                ->when($effectiveTenantId, fn ($query) => $query->where('tenant_id', $effectiveTenantId))
                ->whereIn('sale_id', $saleIds)
                ->where('method', 'cash')
                ->sum('amount'));
        }

        $receivableCashTotal = Decimal::money(
            DB::table('receivable_payments')
                ->when($effectiveTenantId, fn ($query) => $query->where('tenant_id', $effectiveTenantId))
                ->where('shift_id', $shiftId)
                ->where('payment_method', 'cash')
                ->sum('amount')
        );

        $cashMovements = Decimal::add($cashSalesTotal, $receivableCashTotal, Decimal::MONEY_SCALE);

        return [
            'opening_cash' => $openingCash,
            'cash_sales' => $cashSalesTotal,
            'receivable_cash' => $receivableCashTotal,
            'cash_movements' => $cashMovements,
            'expected_cash' => Decimal::add($openingCash, $cashMovements, Decimal::MONEY_SCALE),
        ];
    }

    private function mergeReconciliationNote(string $existingNote, ?string $userNote): string
    {
        $existing = trim($existingNote);
        $note = trim((string) $userNote);

        if ($note === '') {
            return $existing;
        }

        if ($existing === '') {
            return $note;
        }

        return $existing."\nRekonsiliasi kas: ".$note;
    }
}
