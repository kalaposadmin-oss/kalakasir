<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\AccountMapping;
use App\Exceptions\ShiftValidationException;
use App\Models\InventoryBalance;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shift;
use App\Models\StockLedger;
use App\Services\LockPeriodService;
use App\Services\InventoryValuationService;
use App\Support\TenantContext;
use App\Events\SaleCompleted;
use App\Services\AnomalyService;
use App\Services\TransactionDateService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Database\QueryException;
use InvalidArgumentException;

class CheckoutService
{
    public function handle(array $payload, ?string $userId): array
    {
        $tenantId = TenantContext::id();

        if (! $tenantId) {
            throw new InvalidArgumentException('Tenant tidak ditemukan.');
        }

        $this->assertTotals($payload);
        $this->assertShift($payload, $tenantId, $userId);

        $existing = Sale::query()
            ->where('tenant_id', $tenantId)
            ->where('idempotency_key', $payload['idempotency_key'])
            ->first();

        if ($existing) {
            return [
                'sale_id' => $existing->id,
                'idempotent' => true,
            ];
        }

        $now = CarbonImmutable::now('UTC');
        $transactionDate = (new TransactionDateService())
            ->resolve(Arr::get($payload, 'transaction_date'));
        (new LockPeriodService())->ensureNotLocked($transactionDate, $tenantId);

        try {
            return app(DatabaseTransactionService::class)->run(function () use ($payload, $tenantId, $userId, $now, $transactionDate): array {
                $saleId = (string) Str::uuid();
                $items = [];
                $ledgerRows = [];
                $cogsTotal = 0.0;
                $valuationService = app(InventoryValuationService::class);

                foreach ($payload['items'] as $item) {
                    $productId = $item['product_id'];
                    $locationId = $item['location_id'];
                    $qty = (float) $item['qty'];
                    $price = (float) $item['price'];
                    $discount = (float) Arr::get($item, 'discount', 0);

                    $product = Product::query()
                        ->where('tenant_id', $tenantId)
                        ->where('id', $productId)
                        ->firstOrFail();

                    $saleItemId = (string) Str::uuid();

                    $hppSnapshot = (float) $product->hpp_average;

                    if ($product->track_stock) {
                        $affected = DB::update(
                            'UPDATE inventory_balances SET qty_on_hand = qty_on_hand - ? WHERE tenant_id = ? AND product_id = ? AND location_id = ? AND qty_on_hand >= ?',
                            [$qty, $tenantId, $productId, $locationId, $qty]
                        );

                        if ($affected === 0) {
                            app(AnomalyService::class)->raise(
                                tenantId: $tenantId,
                                severity: 'high',
                                type: 'negative_stock_attempt',
                                payload: [
                                    'source' => 'checkout',
                                    'product_id' => $productId,
                                    'location_id' => $locationId,
                                    'requested_qty' => $qty,
                                ]
                            );
                            throw new InsufficientStockException($productId, $locationId);
                        }

                        $balanceAfter = (float) InventoryBalance::query()
                            ->where('tenant_id', $tenantId)
                            ->where('product_id', $productId)
                            ->where('location_id', $locationId)
                            ->value('qty_on_hand');

                        $valuationResult = $valuationService->issue(
                            tenantId: $tenantId,
                            productId: $productId,
                            locationId: $locationId,
                            qty: $qty,
                            referenceType: 'sale',
                            referenceId: $saleId,
                            referenceItemId: $saleItemId,
                            at: $now
                        );

                        $hppSnapshot = (float) $valuationResult['unit_cost'];

                        $ledgerRows[] = [
                            'id' => (string) Str::uuid(),
                            'tenant_id' => $tenantId,
                            'product_id' => $productId,
                            'location_id' => $locationId,
                            'reference_type' => 'sale',
                            'reference_id' => $saleId,
                            'reference_item_id' => $saleItemId,
                            'qty_in' => 0,
                            'qty_out' => $qty,
                            'balance_after' => $balanceAfter,
                            'created_at' => $now,
                            'created_by' => $userId,
                        ];
                    }

                    $items[] = [
                        'id' => $saleItemId,
                        'tenant_id' => $tenantId,
                        'sale_id' => $saleId,
                        'product_id' => $productId,
                        'qty' => $qty,
                        'price' => $price,
                        'discount' => $discount,
                        'hpp_snapshot' => $hppSnapshot,
                    ];

                    $cogsTotal += $hppSnapshot * $qty;
                }

                Sale::query()->create([
                    'id' => $saleId,
                    'tenant_id' => $tenantId,
                    'branch_id' => $payload['branch_id'],
                    'shift_id' => $payload['shift_id'],
                    'customer_id' => Arr::get($payload, 'customer_id'),
                    'subtotal' => (float) $payload['subtotal'],
                    'discount' => (float) $payload['discount'],
                    'tax' => (float) $payload['tax'],
                    'total' => (float) $payload['total'],
                    'status' => 'completed',
                    'idempotency_key' => $payload['idempotency_key'],
                    'created_by' => $userId,
                    'voided_by' => null,
                    'created_at' => $now,
                    'transaction_date' => $transactionDate->toDateString(),
                ]);

                SaleItem::query()->insert($items);
                StockLedger::query()->insert($ledgerRows);

                $paymentRows = [];
                $cashTotal = 0.0;
                $bankTotal = 0.0;

                foreach ($payload['payments'] as $payment) {
                    $amount = (float) $payment['amount'];
                    $method = $payment['method'];

                    if ($method === 'cash') {
                        $cashTotal += $amount;
                    } else {
                        $bankTotal += $amount;
                    }

                    $paymentRows[] = [
                        'id' => (string) Str::uuid(),
                        'tenant_id' => $tenantId,
                        'sale_id' => $saleId,
                        'account_id' => $payment['account_id'],
                        'method' => $method,
                        'amount' => $amount,
                    ];
                }

                Payment::query()->insert($paymentRows);

                $this->createJournalEntries(
                    tenantId: $tenantId,
                    saleId: $saleId,
                    total: (float) $payload['total'],
                    cashTotal: $cashTotal,
                    bankTotal: $bankTotal,
                    cogsTotal: $cogsTotal,
                    userId: $userId,
                    now: $now,
                    transactionDate: $transactionDate
                );

                event(new SaleCompleted(
                    saleId: $saleId,
                    tenantId: $tenantId,
                    total: (float) $payload['total'],
                    actorId: $userId,
                    occurredAt: $now->toIso8601String()
                ));

                return [
                    'sale_id' => $saleId,
                    'idempotent' => false,
                ];
            });
        } catch (QueryException $exception) {
            if ($this->isDuplicateKey($exception)) {
                $existing = Sale::query()
                    ->where('tenant_id', $tenantId)
                    ->where('idempotency_key', $payload['idempotency_key'])
                    ->first();

                if ($existing) {
                    return [
                        'sale_id' => $existing->id,
                        'idempotent' => true,
                    ];
                }
            }

            throw $exception;
        }
    }

    private function assertShift(array $payload, string $tenantId, ?string $userId): void
    {
        if (! $userId) {
            throw new ShiftValidationException('User harus login.', 'UNAUTHENTICATED', 401);
        }

        $shift = Shift::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $payload['shift_id'])
            ->first();

        if (! $shift || $shift->status !== 'open') {
            throw new ShiftValidationException('Shift closed.', 'SHIFT_CLOSED', 422);
        }

        if ($shift->user_id !== $userId) {
            throw new ShiftValidationException('Shift bukan milik kasir ini.', 'SHIFT_USER_MISMATCH', 422);
        }

        if ($shift->branch_id !== $payload['branch_id']) {
            throw new ShiftValidationException('Shift tidak sesuai branch.', 'SHIFT_BRANCH_MISMATCH', 422);
        }
    }

    private function isDuplicateKey(QueryException $exception): bool
    {
        $errorInfo = $exception->errorInfo;

        if (isset($errorInfo[0]) && $errorInfo[0] === '23000') {
            return true;
        }

        if (isset($errorInfo[1]) && (int) $errorInfo[1] === 1062) {
            return true;
        }

        return false;
    }

    private function assertTotals(array $payload): void
    {
        $computedSubtotal = 0.0;

        foreach ($payload['items'] as $item) {
            $qty = (float) $item['qty'];
            $price = (float) $item['price'];
            $discount = (float) Arr::get($item, 'discount', 0);
            $computedSubtotal += ($qty * $price) - $discount;
        }

        $computedSubtotal = round($computedSubtotal, 2);
        $payloadSubtotal = round((float) $payload['subtotal'], 2);

        if ($computedSubtotal !== $payloadSubtotal) {
            throw new InvalidArgumentException('Subtotal tidak valid.');
        }

        $computedTotal = $computedSubtotal - (float) $payload['discount'] + (float) $payload['tax'];
        $computedTotal = round($computedTotal, 2);
        $payloadTotal = round((float) $payload['total'], 2);

        if ($computedTotal !== $payloadTotal) {
            throw new InvalidArgumentException('Total tidak valid.');
        }

        $paymentTotal = 0.0;

        foreach ($payload['payments'] as $payment) {
            $paymentTotal += (float) $payment['amount'];
        }

        if (round($paymentTotal, 2) !== $payloadTotal) {
            throw new InvalidArgumentException('Total pembayaran tidak sama dengan total transaksi.');
        }
    }

    private function createJournalEntries(
        string $tenantId,
        string $saleId,
        float $total,
        float $cashTotal,
        float $bankTotal,
        float $cogsTotal,
        ?string $userId,
        CarbonImmutable $now,
        CarbonImmutable $transactionDate
    ): void {
        $mapping = AccountMapping::query()
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $journalEntryId = (string) Str::uuid();

        JournalEntry::query()->create([
            'id' => $journalEntryId,
            'tenant_id' => $tenantId,
            'reference_type' => 'sale',
            'reference_id' => $saleId,
            'date' => $transactionDate->toDateString(),
            'description' => 'POS sale',
            'status' => 'posted',
            'posted_at' => $now,
            'created_by' => $userId,
            'created_at' => $now,
        ]);

        $journalItems = [];

        if ($cashTotal > 0) {
            $journalItems[] = [
                'id' => (string) Str::uuid(),
                'journal_entry_id' => $journalEntryId,
                'tenant_id' => $tenantId,
                'account_id' => $mapping->cash_account_id,
                'debit' => $cashTotal,
                'credit' => 0,
                'is_reversal' => false,
            ];
        }

        if ($bankTotal > 0) {
            $journalItems[] = [
                'id' => (string) Str::uuid(),
                'journal_entry_id' => $journalEntryId,
                'tenant_id' => $tenantId,
                'account_id' => $mapping->bank_account_id,
                'debit' => $bankTotal,
                'credit' => 0,
                'is_reversal' => false,
            ];
        }

        $journalItems[] = [
            'id' => (string) Str::uuid(),
            'journal_entry_id' => $journalEntryId,
            'tenant_id' => $tenantId,
            'account_id' => $mapping->sale_account_id,
            'debit' => 0,
            'credit' => $total,
            'is_reversal' => false,
        ];

        if ($cogsTotal > 0) {
            $journalItems[] = [
                'id' => (string) Str::uuid(),
                'journal_entry_id' => $journalEntryId,
                'tenant_id' => $tenantId,
                'account_id' => $mapping->cogs_account_id,
                'debit' => $cogsTotal,
                'credit' => 0,
                'is_reversal' => false,
            ];

            $journalItems[] = [
                'id' => (string) Str::uuid(),
                'journal_entry_id' => $journalEntryId,
                'tenant_id' => $tenantId,
                'account_id' => $mapping->inventory_account_id,
                'debit' => 0,
                'credit' => $cogsTotal,
                'is_reversal' => false,
            ];
        }

        JournalItem::query()->insert($journalItems);
    }
}
