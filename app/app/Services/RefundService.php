<?php

namespace App\Services;

use App\Models\AccountMapping;
use App\Models\InventoryBalance;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Refund;
use App\Models\RefundItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockLedger;
use App\Support\Decimal;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use App\Services\DatabaseTransactionService;

class RefundService
{
    public function handle(array $payload, ?string $userId): string
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            throw new InvalidArgumentException('Tenant tidak ditemukan.');
        }

        return $this->performRefund($payload, $userId, $tenantId);
    }

    private function performRefund(array $payload, ?string $userId, string $tenantId): string
    {
        $now = CarbonImmutable::now('UTC');

        return app(DatabaseTransactionService::class)->run(function () use (
            $payload,
            $tenantId,
            $userId,
            $now
        ) {
            $sale = Sale::query()
                ->where('tenant_id', $tenantId)
                ->where('id', $payload['sale_id'])
                ->lockForUpdate()
                ->first();

            if (! $sale || $sale->status !== Sale::STATUS_COMPLETED) {
                throw new InvalidArgumentException('Transaksi tidak dapat direfund.');
            }

            (new LockPeriodService())->ensureNotLocked($now, $tenantId);

            $saleItems = SaleItem::query()
                ->where('tenant_id', $tenantId)
                ->where('sale_id', $sale->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($saleItems->isEmpty()) {
                throw new InvalidArgumentException('Item transaksi tidak ditemukan.');
            }

            $productMap = Product::query()
                ->where('tenant_id', $tenantId)
                ->whereIn('id', $saleItems->pluck('product_id')->unique()->values())
                ->get()
                ->keyBy('id');

            $refundedQtyMap = $this->buildRefundedQtyMap($tenantId, $sale, $saleItems);
            $allocationBasisTotal = $this->allocationBasisTotal($saleItems);
            $saleNetBeforeTax = Decimal::max(
                Decimal::subtract($allocationBasisTotal, $sale->discount, Decimal::MONEY_SCALE),
                0,
                Decimal::MONEY_SCALE
            );

            $preparedItems = [];
            $subtotal = Decimal::money(0);
            $itemDiscountTotal = Decimal::money(0);
            $transactionDiscountTotal = Decimal::money(0);
            $taxTotal = Decimal::money(0);
            $total = Decimal::money(0);

            foreach ($payload['items'] as $item) {
                $saleItem = $this->resolveSaleItem($saleItems, $item);
                $saleItemId = (string) $saleItem->id;
                $qty = Decimal::qty($item['qty']);

                $refundedQty = Decimal::qty($refundedQtyMap[$saleItemId] ?? 0);
                $availableQty = Decimal::subtract($saleItem->qty, $refundedQty, Decimal::QTY_SCALE);

                if (Decimal::compare($qty, 0) <= 0 || Decimal::compare($qty, $availableQty) > 0) {
                    throw new InvalidArgumentException('Qty refund melebihi qty transaksi.');
                }

                $amounts = $this->calculateRefundAmounts(
                    sale: $sale,
                    saleItem: $saleItem,
                    qty: $qty,
                    allocationBasisTotal: $allocationBasisTotal,
                    saleNetBeforeTax: $saleNetBeforeTax,
                );
                $locationId = $this->resolveSaleItemLocationId($tenantId, $saleItemId, (string) $sale->branch_id, (string) Arr::get($payload, 'register_id'));
                $product = $productMap[(string) $saleItem->product_id] ?? null;

                if (! $product) {
                    throw new InvalidArgumentException('Produk refund tidak ditemukan.');
                }

                $preparedItems[] = [
                    'id' => (string) Str::uuid(),
                    'sale_item_id' => $saleItemId,
                    'product_id' => (string) $saleItem->product_id,
                    'product_variant_id' => $saleItem->product_variant_id,
                    'location_id' => $locationId,
                    'qty' => $qty,
                    'unit_price_snapshot' => $amounts['unit_price_snapshot'],
                    'gross_amount' => $amounts['gross_amount'],
                    'item_discount_amount' => $amounts['item_discount_amount'],
                    'transaction_discount_amount' => $amounts['transaction_discount_amount'],
                    'tax_amount' => $amounts['tax_amount'],
                    'net_amount' => $amounts['net_amount'],
                    'amount' => $amounts['net_amount'],
                    'hpp_snapshot' => Decimal::money($saleItem->hpp_snapshot),
                    'track_stock' => (bool) $product->track_stock,
                ];

                $subtotal = Decimal::add($subtotal, $amounts['gross_amount'], Decimal::MONEY_SCALE);
                $itemDiscountTotal = Decimal::add($itemDiscountTotal, $amounts['item_discount_amount'], Decimal::MONEY_SCALE);
                $transactionDiscountTotal = Decimal::add($transactionDiscountTotal, $amounts['transaction_discount_amount'], Decimal::MONEY_SCALE);
                $taxTotal = Decimal::add($taxTotal, $amounts['tax_amount'], Decimal::MONEY_SCALE);
                $total = Decimal::add($total, $amounts['net_amount'], Decimal::MONEY_SCALE);
            }

            $paymentRows = $this->resolveRefundPayments($tenantId, $sale, $payload, Decimal::money($total));
            $payoutTotal = Decimal::money(collect($paymentRows)->reduce(function (string $carry, array $paymentRow) {
                return Decimal::add($carry, $paymentRow['amount'], Decimal::MONEY_SCALE);
            }, Decimal::money(0)));
            if (Decimal::compare($payoutTotal, $total) !== 0) {
                throw new InvalidArgumentException('Total payout refund tidak sama dengan total refund.');
            }

            $shiftId = Arr::get($payload, 'shift_id');
            if ($this->hasCashRefundPayment($paymentRows) && ! $shiftId) {
                throw new InvalidArgumentException('Shift refund wajib diisi untuk payout tunai.');
            }

            $refundId = (string) Str::uuid();
            $valuationService = app(InventoryValuationService::class);
            $refundItems = [];
            $ledgerRows = [];
            $refundPayments = [];
            $cashFlowRows = [];
            $refundedCostTotal = Decimal::money(0);

            Refund::query()->create([
                'id' => $refundId,
                'tenant_id' => $tenantId,
                'sale_id' => $sale->id,
                'register_id' => Arr::get($payload, 'register_id'),
                'shift_id' => $shiftId,
                'branch_id' => $sale->branch_id,
                'created_by' => $userId,
                'subtotal' => Decimal::money($subtotal),
                'item_discount_total' => Decimal::money($itemDiscountTotal),
                'transaction_discount_total' => Decimal::money($transactionDiscountTotal),
                'tax_total' => Decimal::money($taxTotal),
                'refunded_cost_total' => 0,
                'payout_total' => Decimal::money($total),
                'total' => Decimal::money($total),
                'status' => Refund::STATUS_COMPLETED,
                'reason' => Arr::get($payload, 'reason'),
                'transaction_date' => (string) ($sale->transaction_date ?? $now->toDateString()),
                'created_at' => $now,
            ]);

            foreach ($preparedItems as $item) {
                $refundedCost = Decimal::money(0);
                if ($item['track_stock']) {
                    $refundedCost = Decimal::money($valuationService->reverseIssueItem(
                        tenantId: $tenantId,
                        referenceType: 'sale',
                        referenceId: (string) $sale->id,
                        referenceItemId: (string) $item['sale_item_id'],
                        qty: Decimal::float($item['qty'], Decimal::QTY_SCALE),
                        at: $now,
                        newSourceType: 'refund',
                        newSourceId: $refundId,
                    ));

                    $this->incrementInventory(
                        $tenantId,
                        (string) $item['product_id'],
                        $item['product_variant_id'],
                        (string) $item['location_id'],
                        Decimal::float($item['qty'], Decimal::QTY_SCALE)
                    );

                    $balanceAfter = Decimal::qty(InventoryBalance::query()
                        ->where('tenant_id', $tenantId)
                        ->where('product_id', $item['product_id'])
                        ->where('location_id', $item['location_id'])
                        ->when($item['product_variant_id'], function ($query) use ($item) {
                            $query->where('product_variant_id', $item['product_variant_id']);
                        }, function ($query) {
                            $query->whereNull('product_variant_id');
                        })
                        ->value('qty_on_hand'));

                    $ledgerRows[] = [
                        'id' => (string) Str::uuid(),
                        'tenant_id' => $tenantId,
                        'product_id' => $item['product_id'],
                        'product_variant_id' => $item['product_variant_id'],
                        'location_id' => $item['location_id'],
                        'reference_type' => 'refund',
                        'reference_id' => $refundId,
                        'reference_item_id' => $item['sale_item_id'],
                        'qty_in' => $item['qty'],
                        'qty_out' => 0,
                        'balance_after' => $balanceAfter,
                        'created_at' => $now,
                        'created_by' => $userId,
                    ];
                }

                $item['tenant_id'] = $tenantId;
                $item['refund_id'] = $refundId;
                $item['refunded_cost_total'] = Decimal::money($refundedCost);
                $refundItems[] = $item;
                $refundedCostTotal = Decimal::add($refundedCostTotal, $refundedCost, Decimal::MONEY_SCALE);
            }

            foreach ($refundItems as $item) {
                RefundItem::query()->create(Arr::only($item, [
                    'id',
                    'tenant_id',
                    'refund_id',
                    'sale_item_id',
                    'product_id',
                    'product_variant_id',
                    'location_id',
                    'qty',
                    'unit_price_snapshot',
                    'gross_amount',
                    'item_discount_amount',
                    'transaction_discount_amount',
                    'tax_amount',
                    'net_amount',
                    'amount',
                    'hpp_snapshot',
                    'refunded_cost_total',
                ]));
            }

            if (! empty($ledgerRows)) {
                StockLedger::query()->insert($ledgerRows);
            }

            foreach ($paymentRows as $paymentRow) {
                $refundPayments[] = [
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'refund_id' => $refundId,
                    'sale_payment_id' => $paymentRow['sale_payment_id'],
                    'account_id' => $paymentRow['account_id'],
                    'payment_method_id' => $paymentRow['payment_method_id'],
                    'method' => $paymentRow['method'],
                    'amount' => Decimal::money($paymentRow['amount']),
                    'created_at' => $now,
                ];

                if ($paymentRow['method'] === 'cash' && $shiftId) {
                    $cashFlowRows[] = [
                        'id' => (string) Str::uuid(),
                        'tenant_id' => $tenantId,
                        'shift_id' => $shiftId,
                        'branch_id' => $sale->branch_id,
                        'type' => 'refund',
                        'amount' => Decimal::money($paymentRow['amount']),
                        'note' => 'Refund '.$refundId,
                        'created_at' => $now,
                    ];
                }
            }

            if ($refundPayments !== []) {
                DB::table('refund_payments')->insert($refundPayments);
            }

            if ($cashFlowRows !== []) {
                DB::table('cash_flows')->insert($cashFlowRows);
            }

            Refund::query()
                ->where('tenant_id', $tenantId)
                ->where('id', $refundId)
                ->update(['refunded_cost_total' => Decimal::money($refundedCostTotal)]);

            $this->createJournalEntries(
                tenantId: $tenantId,
                refundId: $refundId,
                total: Decimal::float($total, Decimal::MONEY_SCALE),
                refundedCostTotal: Decimal::float($refundedCostTotal, Decimal::MONEY_SCALE),
                paymentRows: $paymentRows,
                userId: $userId,
                now: $now
            );

            DB::table('audit_logs')->insert([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'actor_id' => $userId,
                'event_type' => 'refund_created',
                'reference_type' => 'refund',
                'reference_id' => $refundId,
                'payload_json' => json_encode([
                    'sale_id' => $sale->id,
                    'reason' => Arr::get($payload, 'reason'),
                    'total' => Decimal::float($total, Decimal::MONEY_SCALE),
                ], JSON_UNESCAPED_SLASHES),
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
                'created_at' => $now,
            ]);

            return $refundId;
        });
    }

    private function idempotencyKey(string $tenantId, string $idempotencyKey): string
    {
        return 'pos:refund:'.sha1($tenantId.'|'.$idempotencyKey);
    }

    private function buildRefundedQtyMap(string $tenantId, Sale $sale, Collection $saleItems): array
    {
        $rows = DB::table('refund_items')
            ->join('refunds', function ($join) {
                $join->on('refund_items.refund_id', '=', 'refunds.id')
                    ->on('refund_items.tenant_id', '=', 'refunds.tenant_id');
            })
            ->where('refunds.tenant_id', $tenantId)
            ->where('refunds.sale_id', $sale->id)
            ->select('refund_items.sale_item_id', 'refund_items.product_id')
            ->selectRaw('SUM(refund_items.qty) as qty')
            ->groupBy('refund_items.sale_item_id', 'refund_items.product_id')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            if ($row->sale_item_id) {
                $map[(string) $row->sale_item_id] = Decimal::add($map[(string) $row->sale_item_id] ?? 0, $row->qty, Decimal::QTY_SCALE);
                continue;
            }

            $matchingItems = $saleItems->where('product_id', $row->product_id)->values();
            if ($matchingItems->count() !== 1) {
                throw new InvalidArgumentException('Refund legacy untuk produk duplikat harus dimigrasikan ke sale item sebelum refund baru diproses.');
            }

            $saleItemId = (string) $matchingItems->first()->id;
            $map[$saleItemId] = Decimal::add($map[$saleItemId] ?? 0, $row->qty, Decimal::QTY_SCALE);
        }

        return $map;
    }

    private function resolveSaleItem(Collection $saleItems, array $item): SaleItem
    {
        $saleItemId = (string) Arr::get($item, 'sale_item_id', '');
        if ($saleItemId === '') {
            throw new InvalidArgumentException('sale_item_id wajib diisi untuk refund line item.');
        }

        $saleItem = $saleItems->get($saleItemId);
        if (! $saleItem) {
            throw new InvalidArgumentException('Line item refund tidak ditemukan pada transaksi.');
        }

        $requestedProductId = Arr::get($item, 'product_id');
        if ($requestedProductId !== null && (string) $requestedProductId !== '' && (string) $requestedProductId !== (string) $saleItem->product_id) {
            throw new InvalidArgumentException('product_id refund tidak sesuai sale_item_id.');
        }

        $requestedVariantId = Arr::get($item, 'product_variant_id');
        $saleItemVariantId = $saleItem->product_variant_id ? (string) $saleItem->product_variant_id : null;
        $requestedVariantId = $requestedVariantId !== null && (string) $requestedVariantId !== ''
            ? (string) $requestedVariantId
            : null;

        if ($requestedVariantId !== $saleItemVariantId) {
            throw new InvalidArgumentException('product_variant_id refund tidak sesuai sale_item_id.');
        }

        return $saleItem;
    }

    private function calculateRefundAmounts(
        Sale $sale,
        SaleItem $saleItem,
        string $qty,
        string $allocationBasisTotal,
        string $saleNetBeforeTax
    ): array {
        $saleItemQty = Decimal::qty($saleItem->qty);
        $unitPrice = Decimal::money($saleItem->price);
        $grossAmount = Decimal::multiply($qty, $unitPrice, Decimal::MONEY_SCALE);
        $itemDiscountAmount = Decimal::money($this->prorateAmount($saleItem->discount, $saleItemQty, $qty));
        $lineNetBeforeTransactionDiscount = Decimal::max(
            Decimal::subtract(Decimal::multiply($saleItemQty, $unitPrice, Decimal::MONEY_SCALE), $saleItem->discount, Decimal::MONEY_SCALE),
            0,
            Decimal::MONEY_SCALE
        );
        $fullLineTransactionDiscount = Decimal::compare($allocationBasisTotal, 0) > 0
            ? Decimal::multiply(
                $sale->discount,
                Decimal::divide($lineNetBeforeTransactionDiscount, $allocationBasisTotal, Decimal::RATE_SCALE),
                Decimal::MONEY_SCALE
            )
            : Decimal::money(0);
        $transactionDiscountAmount = Decimal::money($this->prorateAmount($fullLineTransactionDiscount, $saleItemQty, $qty));
        $lineNetAfterDiscount = Decimal::max(
            Decimal::subtract(
                Decimal::subtract($grossAmount, $itemDiscountAmount, Decimal::MONEY_SCALE),
                $transactionDiscountAmount,
                Decimal::MONEY_SCALE
            ),
            0,
            Decimal::MONEY_SCALE
        );
        $fullLineTax = Decimal::compare($saleNetBeforeTax, 0) > 0
            ? Decimal::multiply(
                $sale->tax,
                Decimal::divide(
                    Decimal::max(Decimal::subtract($lineNetBeforeTransactionDiscount, $fullLineTransactionDiscount, Decimal::MONEY_SCALE), 0, Decimal::MONEY_SCALE),
                    $saleNetBeforeTax,
                    Decimal::RATE_SCALE
                ),
                Decimal::MONEY_SCALE
            )
            : Decimal::money(0);
        $taxAmount = Decimal::money($this->prorateAmount($fullLineTax, $saleItemQty, $qty));

        return [
            'unit_price_snapshot' => $unitPrice,
            'gross_amount' => $grossAmount,
            'item_discount_amount' => $itemDiscountAmount,
            'transaction_discount_amount' => $transactionDiscountAmount,
            'tax_amount' => $taxAmount,
            'net_amount' => Decimal::add($lineNetAfterDiscount, $taxAmount, Decimal::MONEY_SCALE),
        ];
    }

    private function allocationBasisTotal(Collection $saleItems): string
    {
        return $saleItems->reduce(function (string $carry, SaleItem $saleItem) {
            $lineAmount = Decimal::max(
                Decimal::subtract(
                    Decimal::multiply($saleItem->qty, $saleItem->price, Decimal::MONEY_SCALE),
                    $saleItem->discount,
                    Decimal::MONEY_SCALE
                ),
                0,
                Decimal::MONEY_SCALE
            );

            return Decimal::add($carry, $lineAmount, Decimal::MONEY_SCALE);
        }, Decimal::money(0));
    }

    private function prorateAmount(mixed $amount, mixed $baseQty, mixed $requestedQty): string
    {
        if (Decimal::compare($amount, 0) <= 0 || Decimal::compare($baseQty, 0) <= 0 || Decimal::compare($requestedQty, 0) <= 0) {
            return Decimal::money(0);
        }

        return Decimal::multiply(
            $amount,
            Decimal::divide($requestedQty, $baseQty, Decimal::RATE_SCALE),
            Decimal::MONEY_SCALE
        );
    }

    private function resolveRefundPayments(string $tenantId, Sale $sale, array $payload, string $refundTotal): array
    {
        $paymentRows = collect((array) Arr::get($payload, 'payments', []))
            ->map(function (array $payment) {
                return [
                    'sale_payment_id' => null,
                    'account_id' => Arr::get($payment, 'account_id'),
                    'payment_method_id' => Arr::get($payment, 'payment_method_id'),
                    'method' => (string) Arr::get($payment, 'method'),
                    'amount' => Decimal::money(Arr::get($payment, 'amount', 0)),
                ];
            })
            ->filter(fn (array $payment) => Decimal::compare($payment['amount'], 0) > 0)
            ->values();

        if ($paymentRows->isNotEmpty()) {
            return $paymentRows->all();
        }

        $salePayments = Payment::query()
            ->where('tenant_id', $tenantId)
            ->where('sale_id', $sale->id)
            ->orderBy('id')
            ->get();

        if ($salePayments->count() !== 1) {
            throw new InvalidArgumentException('Payout refund wajib dipilih untuk transaksi multi-payment.');
        }

        $payment = $salePayments->first();

        return [[
            'sale_payment_id' => $payment->id,
            'account_id' => $payment->account_id,
            'payment_method_id' => $payment->payment_method_id,
            'method' => (string) $payment->method,
            'amount' => $refundTotal,
        ]];
    }

    private function hasCashRefundPayment(array $paymentRows): bool
    {
        return collect($paymentRows)->contains(fn (array $payment) => ($payment['method'] ?? null) === 'cash' && Decimal::compare($payment['amount'] ?? 0, 0) > 0);
    }

    private function resolveSaleItemLocationId(string $tenantId, string $saleItemId, string $branchId, ?string $registerId): string
    {
        $locationId = DB::table('stock_ledger')
            ->where('tenant_id', $tenantId)
            ->where('reference_type', 'sale')
            ->where('reference_item_id', $saleItemId)
            ->orderBy('created_at')
            ->value('location_id');

        if ($locationId) {
            return (string) $locationId;
        }

        $fallback = $this->resolveLocationId($tenantId, $branchId, $registerId);
        if (! $fallback) {
            throw new InvalidArgumentException('Lokasi stok refund tidak ditemukan.');
        }

        return $fallback;
    }

    private function createJournalEntries(
        string $tenantId,
        string $refundId,
        float $total,
        float $refundedCostTotal,
        array $paymentRows,
        ?string $userId,
        CarbonImmutable $now
    ): void {
        $mapping = AccountMapping::query()
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $journalEntryId = (string) Str::uuid();

        JournalEntry::query()->create([
            'id' => $journalEntryId,
            'tenant_id' => $tenantId,
            'reference_type' => 'refund',
            'reference_id' => $refundId,
            'date' => $now->toDateString(),
            'description' => 'POS refund',
            'status' => 'posted',
            'posted_at' => $now,
            'created_by' => $userId,
            'created_at' => $now,
        ]);

        $journalItems = [[
            'id' => (string) Str::uuid(),
            'journal_entry_id' => $journalEntryId,
            'tenant_id' => $tenantId,
            'account_id' => $mapping->sale_account_id,
            'debit' => round($total, 2),
            'credit' => 0,
            'is_reversal' => true,
        ]];

        foreach ($paymentRows as $paymentRow) {
            $accountId = $paymentRow['account_id'] ?: (($paymentRow['method'] ?? null) === 'cash'
                ? $mapping->cash_account_id
                : $mapping->bank_account_id);

            $journalItems[] = [
                'id' => (string) Str::uuid(),
                'journal_entry_id' => $journalEntryId,
                'tenant_id' => $tenantId,
                'account_id' => $accountId,
                'debit' => 0,
                'credit' => round((float) $paymentRow['amount'], 2),
                'is_reversal' => true,
            ];
        }

        if ($refundedCostTotal > 0) {
            $journalItems[] = [
                'id' => (string) Str::uuid(),
                'journal_entry_id' => $journalEntryId,
                'tenant_id' => $tenantId,
                'account_id' => $mapping->inventory_account_id,
                'debit' => round($refundedCostTotal, 2),
                'credit' => 0,
                'is_reversal' => true,
            ];

            $journalItems[] = [
                'id' => (string) Str::uuid(),
                'journal_entry_id' => $journalEntryId,
                'tenant_id' => $tenantId,
                'account_id' => $mapping->cogs_account_id,
                'debit' => 0,
                'credit' => round($refundedCostTotal, 2),
                'is_reversal' => true,
            ];
        }

        JournalItem::query()->insert($journalItems);
    }

    private function resolveLocationId(string $tenantId, string $branchId, ?string $registerId): ?string
    {
        if ($registerId) {
            $register = DB::table('registers')
                ->where('tenant_id', $tenantId)
                ->where('id', $registerId)
                ->first(['id', 'branch_id', 'location_id']);

            if (! $register) {
                throw new InvalidArgumentException('Register tidak ditemukan.');
            }

            if ($register->branch_id !== $branchId) {
                throw new InvalidArgumentException('Register tidak sesuai branch transaksi.');
            }

            if ($register->location_id) {
                return $register->location_id;
            }
        }

        $location = DB::table('locations')
            ->where('tenant_id', $tenantId)
            ->where('branch_id', $branchId)
            ->where('is_default', true)
            ->first(['id']);

        if ($location) {
            return $location->id;
        }

        return DB::table('locations')
            ->where('tenant_id', $tenantId)
            ->where('branch_id', $branchId)
            ->orderBy('name')
            ->value('id');
    }

    private function incrementInventory(
        string $tenantId,
        string $productId,
        ?string $productVariantId,
        string $locationId,
        float $qty
    ): void {
        if ($productVariantId) {
            $affected = DB::update(
                'UPDATE inventory_balances SET qty_on_hand = qty_on_hand + ? WHERE tenant_id = ? AND product_id = ? AND product_variant_id = ? AND location_id = ?',
                [$qty, $tenantId, $productId, $productVariantId, $locationId]
            );

            if ($affected === 0) {
                DB::table('inventory_balances')->insert([
                    'tenant_id' => $tenantId,
                    'location_id' => $locationId,
                    'product_id' => $productId,
                    'product_variant_id' => $productVariantId,
                    'qty_on_hand' => $qty,
                ]);
            }

            return;
        }

        $affected = DB::update(
            'UPDATE inventory_balances SET qty_on_hand = qty_on_hand + ? WHERE tenant_id = ? AND product_id = ? AND location_id = ? AND product_variant_id IS NULL',
            [$qty, $tenantId, $productId, $locationId]
        );

        if ($affected === 0) {
            DB::table('inventory_balances')->insert([
                'tenant_id' => $tenantId,
                'location_id' => $locationId,
                'product_id' => $productId,
                'product_variant_id' => null,
                'qty_on_hand' => $qty,
            ]);
        }
    }
}
