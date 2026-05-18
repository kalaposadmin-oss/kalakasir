<?php

namespace App\Services;

use App\Models\AccountMapping;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\Receivable;
use App\Models\ReceivablePayment;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ReceivableService
{
    public function createForSale(
        string $tenantId,
        string $customerId,
        string $saleId,
        mixed $totalAmount,
        mixed $paidAmount,
        ?string $dueDate,
        ?string $userId,
        CarbonImmutable $now
    ): Receivable {
        $total = Decimal::money($totalAmount);
        $paid = Decimal::money($paidAmount);
        $remaining = Decimal::subtract($total, $paid, Decimal::MONEY_SCALE);

        if (Decimal::compare($remaining, 0) <= 0) {
            throw new InvalidArgumentException('Sisa piutang harus lebih dari nol.');
        }

        if (trim($customerId) === '') {
            throw new InvalidArgumentException('Customer wajib dipilih untuk transaksi piutang.');
        }

        return Receivable::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'customer_id' => $customerId,
            'sale_id' => $saleId,
            'total_amount' => $total,
            'paid_amount' => $paid,
            'remaining_amount' => $remaining,
            'due_date' => $dueDate ?: null,
            'status' => $this->statusForAmounts($paid, $remaining),
            'created_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function recordPayment(
        string $tenantId,
        string $receivableId,
        mixed $amount,
        string $paymentMethod,
        string $accountId,
        ?string $paymentMethodId,
        ?string $notes,
        ?string $userId,
        ?string $shiftId = null
    ): array {
        return app(DatabaseTransactionService::class)->run(function () use (
            $tenantId,
            $receivableId,
            $amount,
            $paymentMethod,
            $accountId,
            $paymentMethodId,
            $notes,
            $userId,
            $shiftId
        ): array {
            $now = CarbonImmutable::now('UTC');
            $paymentAmount = Decimal::money($amount);

            if (Decimal::compare($paymentAmount, 0) <= 0) {
                throw new InvalidArgumentException('Nominal pembayaran piutang harus lebih dari nol.');
            }

            $receivable = Receivable::query()
                ->where('tenant_id', $tenantId)
                ->where('id', $receivableId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($receivable->status === Receivable::STATUS_PAID || Decimal::compare($receivable->remaining_amount, 0) <= 0) {
                throw new InvalidArgumentException('Piutang sudah lunas.');
            }

            if (Decimal::compare($paymentAmount, $receivable->remaining_amount) > 0) {
                throw new InvalidArgumentException('Nominal pembayaran melebihi sisa piutang.');
            }

            $payment = ReceivablePayment::query()->create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'receivable_id' => $receivable->id,
                'customer_id' => $receivable->customer_id,
                'sale_id' => $receivable->sale_id,
                'shift_id' => $shiftId,
                'account_id' => $accountId,
                'payment_method_id' => $paymentMethodId,
                'payment_method' => $paymentMethod,
                'amount' => $paymentAmount,
                'notes' => $notes ?: null,
                'created_by' => $userId,
                'created_at' => $now,
            ]);

            $paid = Decimal::add($receivable->paid_amount, $paymentAmount, Decimal::MONEY_SCALE);
            $remaining = Decimal::subtract($receivable->remaining_amount, $paymentAmount, Decimal::MONEY_SCALE);

            $receivable->forceFill([
                'paid_amount' => $paid,
                'remaining_amount' => $remaining,
                'status' => $this->statusForAmounts($paid, $remaining),
                'updated_at' => $now,
            ])->save();

            $this->createCollectionJournal(
                tenantId: $tenantId,
                paymentId: $payment->id,
                amount: Decimal::float($paymentAmount, Decimal::MONEY_SCALE),
                debitAccountId: $accountId,
                userId: $userId,
                now: $now
            );

            return [
                'receivable' => $receivable->fresh(['customer', 'sale']),
                'payment' => $payment,
            ];
        });
    }

    private function statusForAmounts(mixed $paidAmount, mixed $remainingAmount): string
    {
        if (Decimal::compare($remainingAmount, 0) <= 0) {
            return Receivable::STATUS_PAID;
        }

        if (Decimal::compare($paidAmount, 0) > 0) {
            return Receivable::STATUS_PARTIAL;
        }

        return Receivable::STATUS_UNPAID;
    }

    private function createCollectionJournal(
        string $tenantId,
        string $paymentId,
        float $amount,
        string $debitAccountId,
        ?string $userId,
        CarbonImmutable $now
    ): void {
        $mapping = AccountMapping::query()
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        if (! $mapping->receivable_account_id) {
            throw new InvalidArgumentException('Mapping akun piutang belum disetel.');
        }

        $journalEntryId = (string) Str::uuid();

        JournalEntry::query()->create([
            'id' => $journalEntryId,
            'tenant_id' => $tenantId,
            'reference_type' => 'receivable_payment',
            'reference_id' => $paymentId,
            'date' => $now->setTimezone('Asia/Jakarta')->toDateString(),
            'description' => 'Pembayaran piutang',
            'status' => 'posted',
            'posted_at' => $now,
            'created_by' => $userId,
            'created_at' => $now,
        ]);

        JournalItem::query()->insert([
            [
                'id' => (string) Str::uuid(),
                'journal_entry_id' => $journalEntryId,
                'tenant_id' => $tenantId,
                'account_id' => $debitAccountId,
                'debit' => $amount,
                'credit' => 0,
                'is_reversal' => false,
            ],
            [
                'id' => (string) Str::uuid(),
                'journal_entry_id' => $journalEntryId,
                'tenant_id' => $tenantId,
                'account_id' => $mapping->receivable_account_id,
                'debit' => 0,
                'credit' => $amount,
                'is_reversal' => false,
            ],
        ]);
    }
}
