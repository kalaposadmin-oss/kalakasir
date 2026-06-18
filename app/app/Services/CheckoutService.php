<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\AccountMapping;
use App\Exceptions\ShiftValidationException;
use App\Models\InventoryBalance;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\Discount;
use App\Models\Payment;
use App\Models\PosSavedOrder;
use App\Models\Product;
use App\Models\Receivable;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shift;
use App\Models\Tenant;
use App\Models\StockLedger;
use App\Services\LockPeriodService;
use App\Services\InventoryValuationService;
use App\Services\AutoConsumeOnSellService;
use App\Services\PosSavedOrderInventoryService;
use App\Support\Decimal;
use App\Support\TenantContext;
use App\Events\SaleCompleted;
use App\Services\AnomalyService;
use App\Services\TransactionDateService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

        $this->assertSellableProducts($payload, $tenantId);

        $discountCalculation = app(DiscountCalculationService::class)->calculate($payload);
        $this->assertTotals($payload, $discountCalculation);
        $this->assertShift($payload, $tenantId, $userId);

        $existing = Sale::query()
            ->where('tenant_id', $tenantId)
            ->where('idempotency_key', $payload['idempotency_key'])
            ->first();

        if ($existing) {
            return $this->checkoutResultForExisting($tenantId, $existing->id);
        }

        $now = CarbonImmutable::now('UTC');
        $transactionDate = (new TransactionDateService())
            ->resolve(Arr::get($payload, 'transaction_date'));
        (new LockPeriodService())->ensureNotLocked($transactionDate, $tenantId);

        try {
            return app(DatabaseTransactionService::class)->run(function () use ($payload, $tenantId, $userId, $now, $transactionDate, $discountCalculation): array {
                $this->releaseSavedOrderReservationIfNeeded($payload, $tenantId, $userId, $now);
                $this->replaceExistingSaleIfNeeded($payload, $tenantId, $userId, $now);
                $saleId = (string) Str::uuid();
                $items = [];
                $ledgerRows = [];
                $discountRows = [];
                $valuationService = app(InventoryValuationService::class);
                $autoConsumeService = app(AutoConsumeOnSellService::class);
                $salesCostAggregationService = app(SalesCostAggregationService::class);

                foreach ($payload['items'] as $index => $item) {
                    $productId = $item['product_id'];
                    $locationId = $item['location_id'];
                    $productVariantId = $this->resolveVariantId($tenantId, $productId, Arr::get($item, 'product_variant_id'));
                    $qty = Decimal::qty($item['qty']);
                    $price = Decimal::money($item['price']);
                    $calculatedItem = $discountCalculation['items'][$index] ?? null;
                    $discount = Decimal::money(Arr::get($calculatedItem, 'applied_discount_total', Arr::get($item, 'discount', 0)));

                    $product = Product::query()
                        ->where('tenant_id', $tenantId)
                        ->where('id', $productId)
                        ->firstOrFail();

                    $itemType = ProductItemTypeMapper::resolve(
                        $product->item_type,
                        (string) ($product->product_mode ?? 'default')
                    );

                    $saleItemId = (string) Str::uuid();

                    $hppSnapshot = Decimal::cost($product->hpp_average);

                    if ($product->track_stock) {
                        $affected = $this->decrementInventory(
                            tenantId: $tenantId,
                            productId: $productId,
                            productVariantId: $productVariantId,
                            locationId: $locationId,
                            qty: $qty
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
                                    'requested_qty' => Decimal::float($qty, Decimal::QTY_SCALE),
                                ]
                            );
                            throw new InsufficientStockException($productId, $locationId);
                        }

                        $balanceAfter = Decimal::qty(InventoryBalance::query()
                            ->where('tenant_id', $tenantId)
                            ->where('product_id', $productId)
                            ->where('location_id', $locationId)
                            ->when($productVariantId, function ($query) use ($productVariantId) {
                                $query->where('product_variant_id', $productVariantId);
                            }, function ($query) {
                                $query->whereNull('product_variant_id');
                            })
                            ->value('qty_on_hand'));

                        $valuationResult = $valuationService->issue(
                            tenantId: $tenantId,
                            productId: $productId,
                            productVariantId: $productVariantId,
                            locationId: $locationId,
                            qty: $qty,
                            referenceType: 'sale',
                            referenceId: $saleId,
                            referenceItemId: $saleItemId,
                            at: $now
                        );

                        $hppSnapshot = Decimal::cost($valuationResult['unit_cost']);

                        $ledgerRows[] = [
                            'id' => (string) Str::uuid(),
                            'tenant_id' => $tenantId,
                            'product_id' => $productId,
                            'product_variant_id' => $productVariantId,
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

                    $autoConsumeService->consumeForSaleItem(
                        tenantId: $tenantId,
                        saleId: $saleId,
                        saleItemId: $saleItemId,
                        soldProduct: $product,
                        soldQty: Decimal::float($qty, Decimal::QTY_SCALE),
                        locationId: $locationId,
                        userId: $userId,
                        at: $now
                    );

                    $items[] = [
                        'id' => $saleItemId,
                        'tenant_id' => $tenantId,
                        'sale_id' => $saleId,
                        'product_id' => $productId,
                        'product_variant_id' => $productVariantId,
                        'qty' => $qty,
                        'price' => $price,
                        'discount' => $discount,
                        'hpp_snapshot' => $hppSnapshot,
                    ];

                    foreach ((array) Arr::get($calculatedItem, 'discount_breakdown', []) as $discountLine) {
                        $discountRows[] = $this->makeDiscountRow(
                            tenantId: $tenantId,
                            saleId: $saleId,
                            now: $now,
                            type: sprintf('item_%s', $discountLine['type']),
                            value: Decimal::float($discountLine['applied_value'], Decimal::MONEY_SCALE),
                            notes: [
                                'scope' => 'item',
                                'sale_item_id' => $saleItemId,
                                'product_id' => $productId,
                                'label' => $discountLine['label'],
                                'source' => $discountLine['source'],
                                'reference_id' => $discountLine['reference_id'],
                                'input_value' => $discountLine['value'],
                                'basis_amount' => $discountLine['basis_amount'],
                                'sequence' => $discountLine['sequence'],
                            ],
                        );
                    }

                }

                Sale::query()->create([
                    'id' => $saleId,
                    'tenant_id' => $tenantId,
                    'branch_id' => $payload['branch_id'],
                    'shift_id' => $payload['shift_id'],
                    'register_id' => $payload['register_id'],
                    'customer_id' => Arr::get($payload, 'customer_id'),
                    'order_holder_name' => Arr::get($payload, 'order_holder_name'),
                    'order_note' => Arr::get($payload, 'order_note'),
                    'subtotal' => Decimal::float($discountCalculation['subtotal'], Decimal::MONEY_SCALE),
                    'discount' => Decimal::float($discountCalculation['transaction_discount_total'], Decimal::MONEY_SCALE),
                    'tax' => Decimal::float($payload['tax'], Decimal::MONEY_SCALE),
                    'total' => Decimal::float($payload['total'], Decimal::MONEY_SCALE),
                    'status' => Sale::STATUS_COMPLETED,
                    'idempotency_key' => $payload['idempotency_key'],
                    'created_by' => $userId,
                    'voided_by' => null,
                    'created_at' => $now,
                    'transaction_date' => $transactionDate->toDateString(),
                ]);

                SaleItem::query()->insert($items);
                StockLedger::query()->insert($ledgerRows);

                $costAggregate = $salesCostAggregationService->captureForSale(
                    tenantId: $tenantId,
                    saleId: $saleId,
                    now: $now,
                    source: 'checkout'
                );

                foreach ((array) $discountCalculation['transaction_discounts'] as $discountLine) {
                    $discountRows[] = $this->makeDiscountRow(
                        tenantId: $tenantId,
                        saleId: $saleId,
                        now: $now,
                        type: sprintf('transaction_%s', $discountLine['type']),
                        value: Decimal::float($discountLine['applied_value'], Decimal::MONEY_SCALE),
                        notes: [
                            'scope' => 'transaction',
                            'label' => $discountLine['label'],
                            'source' => $discountLine['source'],
                            'reference_id' => $discountLine['reference_id'],
                            'input_value' => $discountLine['value'],
                            'basis_amount' => $discountLine['basis_amount'],
                            'sequence' => $discountLine['sequence'],
                        ],
                    );
                }

                if ($discountRows !== []) {
                    Discount::query()->insert($discountRows);
                }

                $paymentRows = [];
                $cashTotal = Decimal::money(0);
                $bankTotal = Decimal::money(0);
                $paymentTotal = Decimal::money(0);

                foreach (Arr::get($payload, 'payments', []) as $payment) {
                    $amount = Decimal::money($payment['amount']);
                    $method = $payment['method'];
                    $paymentMethodId = $this->resolvePaymentMethodId($tenantId, Arr::get($payment, 'payment_method_id'), $method);
                    $paymentTotal = Decimal::add($paymentTotal, $amount, Decimal::MONEY_SCALE);

                    if ($method === 'cash') {
                        $cashTotal = Decimal::add($cashTotal, $amount, Decimal::MONEY_SCALE);
                    } else {
                        $bankTotal = Decimal::add($bankTotal, $amount, Decimal::MONEY_SCALE);
                    }

                    $paymentRows[] = [
                        'id' => (string) Str::uuid(),
                        'tenant_id' => $tenantId,
                        'sale_id' => $saleId,
                        'account_id' => $payment['account_id'],
                        'method' => $method,
                        'amount' => $amount,
                        'payment_method_id' => $paymentMethodId,
                    ];
                }

                if ($paymentRows !== []) {
                    Payment::query()->insert($paymentRows);
                }

                $paymentMode = $this->resolvePaymentMode($payload);
                $receivable = null;
                $receivableTotal = Decimal::money(0);

                if ($this->isPartialPaymentMode($paymentMode)) {
                    $receivableTotal = Decimal::subtract(Decimal::money($payload['total']), $paymentTotal, Decimal::MONEY_SCALE);
                    $receivable = app(ReceivableService::class)->createForSale(
                        tenantId: $tenantId,
                        customerId: (string) Arr::get($payload, 'customer_id'),
                        saleId: $saleId,
                        totalAmount: $payload['total'],
                        paidAmount: $paymentTotal,
                        dueDate: Arr::get($payload, 'due_date'),
                        userId: $userId,
                        now: $now
                    );
                }

                $this->createJournalEntries(
                    tenantId: $tenantId,
                    saleId: $saleId,
                    total: Decimal::float($payload['total'], Decimal::MONEY_SCALE),
                    cashTotal: Decimal::float($cashTotal, Decimal::MONEY_SCALE),
                    bankTotal: Decimal::float($bankTotal, Decimal::MONEY_SCALE),
                    receivableTotal: Decimal::float($receivableTotal, Decimal::MONEY_SCALE),
                    cogsTotal: (float) $costAggregate['total_cogs'],
                    userId: $userId,
                    now: $now,
                    transactionDate: $transactionDate
                );

                $requestId = app()->bound('request') ? (string) request()->header('X-Request-Id') : null;
                $requestId = $requestId !== '' ? $requestId : null;

                event(new SaleCompleted(
                    saleId: $saleId,
                    tenantId: $tenantId,
                    total: Decimal::float($payload['total'], Decimal::MONEY_SCALE),
                    actorId: $userId,
                    occurredAt: $now->toIso8601String(),
                    requestId: $requestId
                ));

                return [
                    'sale_id' => $saleId,
                    'idempotent' => false,
                    'receivable_id' => $receivable?->id,
                    'payment_status' => $receivable ? $receivable->status : 'paid',
                ];
            });
        } catch (QueryException $exception) {
            if ($this->isDuplicateKey($exception)) {
                $existing = Sale::query()
                    ->where('tenant_id', $tenantId)
                    ->where('idempotency_key', $payload['idempotency_key'])
                    ->first();

                if ($existing) {
                    return $this->checkoutResultForExisting($tenantId, $existing->id);
                }
            }

            throw $exception;
        }
    }

    private function replaceExistingSaleIfNeeded(array $payload, string $tenantId, ?string $userId, CarbonImmutable $now): void
    {
        $replaceSaleId = trim((string) Arr::get($payload, 'replace_sale_id', ''));
        if ($replaceSaleId === '') {
            return;
        }

        $sale = Sale::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $replaceSaleId)
            ->lockForUpdate()
            ->firstOrFail();

        if ($sale->status !== Sale::STATUS_COMPLETED) {
            throw new InvalidArgumentException('Transaksi yang dipilih tidak bisa diedit.');
        }

        $hasPayments = Payment::query()
            ->where('tenant_id', $tenantId)
            ->where('sale_id', $replaceSaleId)
            ->exists();

        $hasReceivablePayments = DB::table('receivable_payments')
            ->where('tenant_id', $tenantId)
            ->where('sale_id', $replaceSaleId)
            ->exists();

        if ($hasPayments || $hasReceivablePayments) {
            throw new InvalidArgumentException('Transaksi yang sudah punya pembayaran tidak bisa diedit langsung.');
        }

        $receivable = Receivable::query()
            ->where('tenant_id', $tenantId)
            ->where('sale_id', $replaceSaleId)
            ->lockForUpdate()
            ->first();

        app(VoidService::class)->handle(
            saleId: $replaceSaleId,
            userId: $userId,
            reason: 'Edit transaksi',
            managerPin: null,
            clientIp: null,
            userAgent: null,
            requestId: null,
            idempotencyKey: 'replace:'.$replaceSaleId.':'.(string) Arr::get($payload, 'idempotency_key', '')
        );

        if ($receivable) {
            $receivable->payments()->delete();
            $receivable->delete();
        }
    }

    private function releaseSavedOrderReservationIfNeeded(array $payload, string $tenantId, ?string $userId, CarbonImmutable $now): void
    {
        $savedOrderId = trim((string) Arr::get($payload, 'saved_order_id', ''));
        if ($savedOrderId === '') {
            return;
        }

        $order = PosSavedOrder::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $savedOrderId)
            ->lockForUpdate()
            ->first();

        if (! $order) {
            return;
        }

        $items = (array) (($order->payload ?? [])['items'] ?? []);

        app(PosSavedOrderInventoryService::class)->release(
            order: $order,
            items: $items,
            userId: $userId,
            now: $now
        );

        $order->delete();
    }

    private function assertShift(array $payload, string $tenantId, ?string $userId): void
    {
        if (! $userId) {
            throw new ShiftValidationException('User harus login.', 'UNAUTHENTICATED', 401);
        }

        $tenant = Tenant::query()->find($tenantId);
        app(ShiftLifecycleService::class)->autoCloseExpiredOpenShifts($tenant);

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

        if (app(ShiftLifecycleService::class)->pendingReconciliation($tenant, $userId)) {
            throw new ShiftValidationException('Selesaikan kas akhir shift kemarin terlebih dahulu.', 'SHIFT_RECONCILIATION_REQUIRED', 422);
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

    private function checkoutResultForExisting(string $tenantId, string $saleId): array
    {
        if (! Schema::hasTable('receivables')) {
            return [
                'sale_id' => $saleId,
                'idempotent' => true,
                'receivable_id' => null,
                'payment_status' => 'paid',
            ];
        }

        $receivable = Receivable::query()
            ->where('tenant_id', $tenantId)
            ->where('sale_id', $saleId)
            ->first(['id', 'status']);

        return [
            'sale_id' => $saleId,
            'idempotent' => true,
            'receivable_id' => $receivable?->id,
            'payment_status' => $receivable?->status ?? 'paid',
        ];
    }

    private function resolvePaymentMode(array $payload): string
    {
        $mode = strtolower(trim((string) Arr::get($payload, 'payment_mode', 'full')));

        return in_array($mode, ['partial', 'receivable'], true) ? 'partial' : 'full';
    }

    private function isPartialPaymentMode(string $paymentMode): bool
    {
        return $paymentMode === 'partial';
    }

    private function assertTotals(array $payload, array $discountCalculation): void
    {
        $computedSubtotal = Decimal::money($discountCalculation['subtotal'] ?? 0);
        $payloadSubtotal = Decimal::money($payload['subtotal']);

        if (Decimal::compare($computedSubtotal, $payloadSubtotal) !== 0) {
            throw new InvalidArgumentException('Subtotal tidak valid.');
        }

        $computedDiscount = Decimal::money($discountCalculation['transaction_discount_total'] ?? 0);
        $payloadDiscount = Decimal::money($payload['discount']);

        if (Decimal::compare($computedDiscount, $payloadDiscount) !== 0) {
            throw new InvalidArgumentException('Diskon transaksi tidak valid.');
        }

        $computedTotal = Decimal::add(
            Decimal::subtract($computedSubtotal, $computedDiscount, Decimal::MONEY_SCALE),
            Decimal::money($payload['tax']),
            Decimal::MONEY_SCALE
        );
        $payloadTotal = Decimal::money($payload['total']);

        if (Decimal::compare($computedTotal, $payloadTotal) !== 0) {
            throw new InvalidArgumentException('Total tidak valid.');
        }

        $paymentTotal = Decimal::money(0);

        foreach (Arr::get($payload, 'payments', []) as $payment) {
            $paymentTotal = Decimal::add($paymentTotal, Decimal::money($payment['amount']), Decimal::MONEY_SCALE);
        }

        $paymentMode = $this->resolvePaymentMode($payload);

        if ($this->isPartialPaymentMode($paymentMode)) {
            if (blank(Arr::get($payload, 'customer_id'))) {
                throw new InvalidArgumentException('Customer wajib dipilih untuk transaksi piutang.');
            }

            if (Decimal::compare($paymentTotal, $payloadTotal) >= 0) {
                throw new InvalidArgumentException('Bayar sebagian harus lebih kecil dari total transaksi.');
            }

            return;
        }

        if (Decimal::compare($paymentTotal, $payloadTotal) !== 0) {
            throw new InvalidArgumentException('Total pembayaran tidak sama dengan total transaksi.');
        }
    }

    private function assertSellableProducts(array $payload, string $tenantId): void
    {
        $productIds = collect($payload['items'] ?? [])
            ->pluck('product_id')
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values();

        if ($productIds->isEmpty()) {
            return;
        }

        $products = Product::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $productIds->all())
            ->get(['id', 'item_type', 'product_mode'])
            ->keyBy('id');

        foreach ($productIds as $productId) {
            $product = $products->get($productId);
            if (! $product) {
                continue;
            }

            $productMode = (string) ($product->product_mode ?? 'default');
            $itemType = ProductItemTypeMapper::resolve($product->item_type, $productMode);

            if (ProductItemTypeMapper::isSellable($itemType, $productMode)) {
                continue;
            }

            $label = match ($itemType) {
                ProductItemTypeMapper::ITEM_RAW_MATERIAL => 'raw material',
                ProductItemTypeMapper::ITEM_PACKAGING => 'packaging',
                ProductItemTypeMapper::ITEM_SUPPLY => 'perlengkapan',
                ProductItemTypeMapper::ITEM_SMALL_TOOL => 'alat kecil',
                default => 'stok internal',
            };

            throw new InvalidArgumentException("Produk {$label} tidak dapat dijual melalui checkout.");
        }
    }

    private function createJournalEntries(
        string $tenantId,
        string $saleId,
        float $total,
        float $cashTotal,
        float $bankTotal,
        float $receivableTotal,
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

        if ($receivableTotal > 0) {
            if (! $mapping->receivable_account_id) {
                throw new InvalidArgumentException('Mapping akun piutang belum disetel.');
            }

            $journalItems[] = [
                'id' => (string) Str::uuid(),
                'journal_entry_id' => $journalEntryId,
                'tenant_id' => $tenantId,
                'account_id' => $mapping->receivable_account_id,
                'debit' => $receivableTotal,
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

    private function resolveVariantId(string $tenantId, string $productId, ?string $variantId): ?string
    {
        if ($variantId) {
            $exists = DB::table('product_variants')
                ->where('tenant_id', $tenantId)
                ->where('id', $variantId)
                ->where('product_id', $productId)
                ->exists();

            if (! $exists) {
                throw new InvalidArgumentException('Varian produk tidak valid.');
            }

            return $variantId;
        }

        $resolved = DB::table('product_variants')
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->orderBy('created_at')
            ->value('id');

        if ($resolved) {
            throw new InvalidArgumentException('Produk varian memerlukan product_variant_id.');
        }

        return null;
    }

    private function decrementInventory(
        string $tenantId,
        string $productId,
        ?string $productVariantId,
        string $locationId,
        float $qty
    ): int {
        if ($productVariantId) {
            return DB::update(
                'UPDATE inventory_balances SET qty_on_hand = qty_on_hand - ? WHERE tenant_id = ? AND product_id = ? AND product_variant_id = ? AND location_id = ? AND qty_on_hand >= ?',
                [$qty, $tenantId, $productId, $productVariantId, $locationId, $qty]
            );
        }

        return DB::update(
            'UPDATE inventory_balances SET qty_on_hand = qty_on_hand - ? WHERE tenant_id = ? AND product_id = ? AND location_id = ? AND product_variant_id IS NULL AND qty_on_hand >= ?',
            [$qty, $tenantId, $productId, $locationId, $qty]
        );
    }

    private function resolvePaymentMethodId(string $tenantId, ?string $paymentMethodId, string $method): ?string
    {
        if ($paymentMethodId) {
            $exists = DB::table('payment_methods')
                ->where('tenant_id', $tenantId)
                ->where('id', $paymentMethodId)
                ->exists();

            if (! $exists) {
                throw new InvalidArgumentException('Metode pembayaran tidak valid.');
            }

            return $paymentMethodId;
        }

        return DB::table('payment_methods')
            ->where('tenant_id', $tenantId)
            ->where('method_type', $method)
            ->orderBy('created_at')
            ->value('id');
    }

    private function makeDiscountRow(
        string $tenantId,
        string $saleId,
        CarbonImmutable $now,
        string $type,
        float $value,
        array $notes
    ): array {
        return [
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'sale_id' => $saleId,
            'type' => $type,
            'value' => Decimal::float($value, Decimal::MONEY_SCALE),
            'notes' => json_encode($notes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => $now,
        ];
    }
}

