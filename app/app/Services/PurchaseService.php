<?php

namespace App\Services;

use App\Models\AccountMapping;
use App\Models\InventoryBalance;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\StockLedger;
use App\Services\LockPeriodService;
use App\Services\InventoryValuationService;
use App\Support\PurchaseMode;
use App\Support\PurchaseFlowResolver;
use App\Support\PurchaseStatus;
use App\Support\Decimal;
use App\Support\TenantContext;
use App\Events\PurchaseReceived;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PurchaseService
{
    private function flowResolver(): PurchaseFlowResolver
    {
        return app(PurchaseFlowResolver::class);
    }

    public function createDraftPurchase(array $payload, ?string $userId): array
    {
        return app(DatabaseTransactionService::class)->run(function () use ($payload): array {
            $purchaseMode = $this->flowResolver()->resolveMode($payload['purchase_mode'] ?? null, TenantContext::get()?->purchase_mode);
            $purchase = $this->persistPurchase($payload, PurchaseStatus::DRAFT, $purchaseMode);

            return [
                'purchase_id' => $purchase->id,
                'status' => $purchase->status,
                'purchase_mode' => $purchase->purchase_mode,
            ];
        });
    }

    public function createDirectPurchase(array $payload, ?string $userId): array
    {
        $tenantId = TenantContext::id();
        $now = CarbonImmutable::now('UTC');
        $transactionDate = (new TransactionDateService())->resolve(Arr::get($payload, 'transaction_date'));
        $lockService = new LockPeriodService();

        return DB::transaction(function () use ($payload, $userId, $tenantId, $now, $lockService, $transactionDate): array {
            $purchase = $this->persistPurchase($payload, PurchaseStatus::DRAFT, PurchaseMode::SIMPLE);
            $receiveResult = $this->performReceive((string) $purchase->id, $payload, $tenantId, $userId, $now, $lockService, $transactionDate, true);

            return [
                'purchase_id' => $purchase->id,
                'status' => $receiveResult['status'],
                'purchase_mode' => $purchase->purchase_mode,
                'received_total' => $receiveResult['received_total'],
            ];
        });
    }

    public function createShortPO(array $payload, ?string $userId): array
    {
        return app(DatabaseTransactionService::class)->run(function () use ($payload): array {
            $purchase = $this->persistPurchase($payload, PurchaseStatus::SUBMITTED, PurchaseMode::PO_SHORT);

            return [
                'purchase_id' => $purchase->id,
                'status' => $purchase->status,
                'purchase_mode' => $purchase->purchase_mode,
            ];
        });
    }

    public function createControlledPO(array $payload, ?string $userId): array
    {
        return app(DatabaseTransactionService::class)->run(function () use ($payload): array {
            $purchase = $this->persistPurchase($payload, PurchaseStatus::SUBMITTED, PurchaseMode::CONTROLLED);

            return [
                'purchase_id' => $purchase->id,
                'status' => $purchase->status,
                'purchase_mode' => $purchase->purchase_mode,
            ];
        });
    }

    public function approve(string $purchaseId, ?string $userId): void
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            throw new RuntimeException('Tenant tidak ditemukan.');
        }

        $purchase = Purchase::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $purchaseId)
            ->firstOrFail();

        $purchaseMode = $this->flowResolver()->resolveMode($purchase->purchase_mode ?? null, TenantContext::get()?->purchase_mode);

        if (! $this->flowResolver()->canApprove((string) $purchase->status, $purchaseMode)) {
            if ($purchaseMode !== PurchaseMode::CONTROLLED) {
                throw new RuntimeException('Approval hanya aktif pada mode controlled.');
            }

            throw new RuntimeException('PO harus berstatus submitted sebelum disetujui.');
        }

        $purchase->update([
            'status' => PurchaseStatus::APPROVED,
            'approved_at' => CarbonImmutable::now('UTC'),
            'approved_by' => $userId,
        ]);
    }

    public function cancel(string $purchaseId, ?string $userId): void
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            throw new RuntimeException('Tenant tidak ditemukan.');
        }

        $purchase = Purchase::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $purchaseId)
            ->firstOrFail();

        if (! PurchaseStatus::canCancel((string) $purchase->status)) {
            throw new RuntimeException('Purchase tidak bisa dibatalkan.');
        }

        $purchase->update([
            'status' => PurchaseStatus::CANCELLED,
            'cancelled_at' => CarbonImmutable::now('UTC'),
            'cancelled_by' => $userId,
        ]);
    }

    public function close(string $purchaseId, ?string $userId): void
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            throw new RuntimeException('Tenant tidak ditemukan.');
        }

        $purchase = Purchase::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $purchaseId)
            ->firstOrFail();

        $purchaseMode = $this->flowResolver()->resolveMode($purchase->purchase_mode ?? null, TenantContext::get()?->purchase_mode);

        if (! PurchaseStatus::canClose((string) $purchase->status, $purchaseMode)) {
            throw new RuntimeException('Hanya PO yang diterima sebagian yang bisa ditutup tanpa susulan.');
        }

        $purchase->update([
            'status' => PurchaseStatus::CLOSED,
            'closed_at' => CarbonImmutable::now('UTC'),
            'closed_by' => $userId,
        ]);
    }

    public function updatePayment(string $purchaseId, array $payload, ?string $userId = null): array
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            throw new RuntimeException('Tenant tidak ditemukan.');
        }

        return DB::transaction(function () use ($tenantId, $purchaseId, $payload, $userId): array {
            $purchase = Purchase::query()
                ->where('tenant_id', $tenantId)
                ->where('id', $purchaseId)
                ->lockForUpdate()
                ->firstOrFail();

            $normalizedStatus = PurchaseStatus::normalize((string) $purchase->status);
            if (in_array($normalizedStatus, [PurchaseStatus::CLOSED, PurchaseStatus::CANCELLED], true)) {
                throw new RuntimeException('Pembelian yang sudah ditutup atau dibatalkan tidak bisa diedit.');
            }

            $paymentMethod = $payload['payment_method'] ?? (string) ($purchase->payment_method ?? 'tempo');
            $paymentMethod = in_array($paymentMethod, ['tunai', 'tempo'], true) ? $paymentMethod : 'tempo';

            $dueDate = $payload['due_date'] ?? null;
            $cashAccountId = $payload['cash_account_id'] ?? null;

            if ($paymentMethod === 'tempo') {
                $dueDate = $dueDate ?: ($purchase->due_date ?: CarbonImmutable::now('UTC')->addDays(14)->toDateString());
                $cashAccountId = null;
            } else {
                $cashAccountId = $cashAccountId ?: ($purchase->cash_account_id ?: $this->resolveDefaultCashAccountId($tenantId));
                $dueDate = null;

                if (! $cashAccountId) {
                    throw new RuntimeException('Akun kas belum diatur. Lengkapi mapping akun kas terlebih dahulu.');
                }
            }

            $purchase->update([
                'payment_method' => $paymentMethod,
                'due_date' => $dueDate,
                'cash_account_id' => $cashAccountId,
            ]);

            app(PurchasePaymentCorrectionService::class)->sync(
                $tenantId,
                (string) $purchase->id,
                $userId,
                'live'
            );

            $freshPurchase = $purchase->fresh();

            return [
                'id' => (string) $freshPurchase->id,
                'payment_method' => (string) $freshPurchase->payment_method,
                'due_date' => $freshPurchase->due_date,
                'cash_account_id' => $freshPurchase->cash_account_id ? (string) $freshPurchase->cash_account_id : null,
            ];
        });
    }

    public function updateItemPrice(string $purchaseId, string $purchaseItemId, float $price): array
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            throw new RuntimeException('Tenant tidak ditemukan.');
        }

        return DB::transaction(function () use ($tenantId, $purchaseId, $purchaseItemId, $price): array {
            $purchase = Purchase::query()
                ->where('tenant_id', $tenantId)
                ->where('id', $purchaseId)
                ->lockForUpdate()
                ->firstOrFail();

            $normalizedStatus = PurchaseStatus::normalize((string) $purchase->status);
            if (in_array($normalizedStatus, [PurchaseStatus::RECEIVED, PurchaseStatus::PARTIALLY_RECEIVED, PurchaseStatus::CLOSED, PurchaseStatus::CANCELLED], true)) {
                throw new RuntimeException('Harga hanya bisa diubah sebelum penerimaan barang dimulai.');
            }

            $item = PurchaseItem::query()
                ->where('tenant_id', $tenantId)
                ->where('purchase_id', $purchase->id)
                ->where('id', $purchaseItemId)
                ->lockForUpdate()
                ->firstOrFail();

            if ((float) ($item->received_qty ?? 0) > 0) {
                throw new RuntimeException('Harga hanya bisa diubah sebelum penerimaan barang dimulai.');
            }

            $multiplier = Decimal::qty($item->purchase_unit_multiplier ?? 1);
            if (Decimal::compare($multiplier, '0') <= 0) {
                $multiplier = Decimal::qty(1);
            }

            $item->update([
                'price' => Decimal::float(Decimal::divide($price, $multiplier, Decimal::COST_SCALE), Decimal::COST_SCALE),
                'purchase_price' => $price,
            ]);

            $purchaseTotal = (float) (PurchaseItem::query()
                ->where('tenant_id', $tenantId)
                ->where('purchase_id', $purchase->id)
                ->selectRaw('COALESCE(SUM(qty * price), 0) as total')
                ->value('total') ?? 0);

            $purchase->update([
                'total' => $purchaseTotal,
            ]);

            return [
                'purchase' => $purchase->fresh(),
                'item' => $item->fresh(),
            ];
        });
    }

    public function updateTax(string $purchaseId, array $payload, ?string $userId = null): array
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            throw new RuntimeException('Tenant tidak ditemukan.');
        }

        if (! Schema::hasColumn('purchases', 'tax')) {
            throw new RuntimeException('Kolom pajak pembelian belum tersedia. Jalankan migrasi database terlebih dahulu.');
        }

        return DB::transaction(function () use ($tenantId, $purchaseId, $payload, $userId): array {
            $purchase = Purchase::query()
                ->where('tenant_id', $tenantId)
                ->where('id', $purchaseId)
                ->lockForUpdate()
                ->firstOrFail();

            $normalizedStatus = PurchaseStatus::normalize((string) $purchase->status);
            if (in_array($normalizedStatus, [PurchaseStatus::CLOSED, PurchaseStatus::CANCELLED], true)) {
                throw new RuntimeException('Pajak pembelian yang sudah ditutup atau dibatalkan tidak bisa diedit.');
            }

            $itemSubtotal = (float) (PurchaseItem::query()
                ->where('tenant_id', $tenantId)
                ->where('purchase_id', $purchase->id)
                ->selectRaw('COALESCE(SUM(purchase_qty * purchase_price), SUM(qty * price), 0) as total')
                ->value('total') ?? 0);
            $subtotal = round($itemSubtotal > 0 ? $itemSubtotal : (float) ($purchase->subtotal ?? $purchase->total ?? 0), 2);
            $taxEnabled = (bool) ($payload['tax_enabled'] ?? false);
            $taxRate = $taxEnabled ? (float) ($payload['tax_rate'] ?? 0) : 0.0;
            $tax = $taxEnabled ? (float) ($payload['tax'] ?? round($subtotal * ($taxRate / 100), 2)) : 0.0;
            $tax = max(0.0, round($tax, 2));
            $total = round($subtotal + $tax, 2);

            $purchase->update([
                'subtotal' => $subtotal,
                'tax_rate' => $taxRate,
                'tax' => $tax,
                'total' => $total,
            ]);

            app(PurchasePaymentCorrectionService::class)->sync(
                $tenantId,
                (string) $purchase->id,
                $userId,
                'tax_update'
            );

            return [
                'purchase' => $purchase->fresh(),
            ];
        });
    }

    public function receive(string $purchaseId, array $payload, ?string $userId): array
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            throw new RuntimeException('Tenant tidak ditemukan.');
        }

        $now = CarbonImmutable::now('UTC');
        $transactionDate = (new TransactionDateService())
            ->resolve(Arr::get($payload, 'transaction_date'));
        $lockService = new LockPeriodService();

        return app(DatabaseTransactionService::class)->run(function () use ($purchaseId, $payload, $tenantId, $userId, $now, $lockService, $transactionDate): array {
            return $this->performReceive($purchaseId, $payload, $tenantId, $userId, $now, $lockService, $transactionDate, false);
        });
    }

    private function persistPurchase(array $payload, string $status, string $purchaseMode): Purchase
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            throw new RuntimeException('Tenant tidak ditemukan.');
        }

        $purchaseId = (string) Str::uuid();
        Purchase::query()->create([
            'id' => $purchaseId,
            'tenant_id' => $tenantId,
            'supplier_id' => $payload['supplier_id'],
            'location_id' => $payload['location_id'] ?? null,
            'total' => $payload['total'],
            'status' => $status,
            'payment_method' => $payload['payment_method'] ?? 'tempo',
            'due_date' => $payload['due_date'] ?? null,
            'cash_account_id' => $payload['cash_account_id'] ?? null,
            'notes' => $payload['notes'] ?? null,
            'created_at' => CarbonImmutable::now('UTC'),
            'transaction_date' => $payload['transaction_date'] ?? CarbonImmutable::now('UTC')->toDateString(),
        ] + (Schema::hasColumn('purchases', 'subtotal') ? ['subtotal' => $payload['subtotal'] ?? $payload['total']] : [])
            + (Schema::hasColumn('purchases', 'tax_rate') ? ['tax_rate' => $payload['tax_rate'] ?? 0] : [])
            + (Schema::hasColumn('purchases', 'tax') ? ['tax' => $payload['tax'] ?? 0] : [])
            + (Schema::hasColumn('purchases', 'purchase_mode') ? ['purchase_mode' => $purchaseMode] : []));

        $conversion = app(UnitConversionService::class);
        $productIds = collect($payload['items'] ?? [])->pluck('product_id')->filter()->unique()->values()->all();
        $products = Product::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $productIds)
            ->get(['id', 'unit'])
            ->keyBy('id');

        $items = collect($payload['items'] ?? [])->map(function ($item, int $index) use ($tenantId, $purchaseId, $products, $conversion) {
            $product = $products->get($item['product_id']);
            if (! $product) {
                throw ValidationException::withMessages([
                    "items.$index.product_id" => 'Produk tidak ditemukan untuk tenant aktif.',
                ]);
            }

            $purchaseQty = Decimal::qty($item['qty'] ?? 0);
            $baseUnit = $product->unit ?: null;
            $purchaseUnit = $conversion->displayUnit($item['purchase_unit'] ?? $baseUnit) ?: $baseUnit;
            $baseQty = $conversion->convertQuantity($purchaseQty, $purchaseUnit, $baseUnit, "items.$index.purchase_unit");
            $multiplier = Decimal::compare($purchaseQty, '0') > 0
                ? Decimal::divide($baseQty, $purchaseQty, Decimal::QTY_SCALE)
                : Decimal::qty(1);
            $purchasePrice = Decimal::money($item['price'] ?? 0);
            $baseUnitCost = Decimal::compare($multiplier, '0') > 0
                ? Decimal::divide($purchasePrice, $multiplier, Decimal::COST_SCALE)
                : Decimal::cost($purchasePrice);

            return [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'purchase_id' => $purchaseId,
                'product_id' => $item['product_id'],
                'qty' => Decimal::float($baseQty, Decimal::QTY_SCALE),
                'price' => Decimal::float($baseUnitCost, Decimal::COST_SCALE),
                'purchase_qty' => Decimal::float($purchaseQty, Decimal::QTY_SCALE),
                'purchase_unit' => $purchaseUnit,
                'purchase_unit_multiplier' => Decimal::float($multiplier, Decimal::QTY_SCALE),
                'purchase_price' => Decimal::float($purchasePrice, Decimal::MONEY_SCALE),
            ];
        })->all();

        PurchaseItem::query()->insert($items);

        return Purchase::query()->where('tenant_id', $tenantId)->where('id', $purchaseId)->firstOrFail();
    }

    private function performReceive(
        string $purchaseId,
        array $payload,
        string $tenantId,
        ?string $userId,
        CarbonImmutable $now,
        LockPeriodService $lockService,
        CarbonImmutable $transactionDate,
        bool $bypassStatusCheck
    ): array {
        $valuationService = app(InventoryValuationService::class);

        $purchase = Purchase::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $purchaseId)
            ->lockForUpdate()
            ->firstOrFail();

        $purchaseMode = $this->flowResolver()->resolveMode($purchase->purchase_mode ?? null, TenantContext::get()?->purchase_mode);

        if (! $bypassStatusCheck && ! $this->flowResolver()->canReceive((string) $purchase->status, $purchaseMode)) {
            throw new RuntimeException($this->flowResolver()->receiveBlockMessage((string) $purchase->status, $purchaseMode));
        }

        if (! $purchase->location_id) {
            throw new RuntimeException('Lokasi purchase belum ditentukan.');
        }

        $lockService->ensureNotLocked($transactionDate, $tenantId);

        $items = PurchaseItem::query()
            ->where('tenant_id', $tenantId)
            ->where('purchase_id', $purchase->id)
            ->get();

        if ($items->isEmpty()) {
            throw new RuntimeException('Item purchase tidak ditemukan.');
        }

        $requested = collect(Arr::get($payload, 'items', []))
            ->filter(fn ($item) => filled($item['product_id'] ?? null))
            ->keyBy('product_id');

        if ($requested->count() !== $items->count()) {
            throw new RuntimeException('Semua item purchase harus dikirim saat penerimaan.');
        }

        $productUnits = Product::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $items->pluck('product_id')->unique()->values()->all())
            ->pluck('unit', 'id');
        $conversion = app(UnitConversionService::class);

        $ledgerRows = [];
        $totalReceivedValue = Decimal::money(0);
        $receivedCount = 0;

        foreach ($items as $item) {
            $remaining = Decimal::subtract($item->qty, $item->received_qty ?? 0, Decimal::QTY_SCALE);
            if (Decimal::compare($remaining, 0) <= 0) {
                continue;
            }

            $requestItem = $requested->get($item->product_id) ?? [];
            $productUnit = $productUnits->get($item->product_id);
            $receiveUnit = $requestItem['purchase_unit']
                ?? $item->purchase_unit
                ?? $productUnit;
            $receiveQty = $conversion->convertQuantity(
                $requestItem['qty'] ?? 0,
                $receiveUnit,
                $productUnit,
                'items.qty'
            );

            if (Decimal::compare($receiveQty, 0) <= 0) {
                continue;
            }

            if (Decimal::compare($receiveQty, $remaining) > 0) {
                throw new RuntimeException('Qty received melebihi sisa untuk produk tertentu.');
            }

            $item->update([
                'received_qty' => Decimal::add($item->received_qty ?? 0, $receiveQty, Decimal::QTY_SCALE),
            ]);

            InventoryBalance::query()->firstOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'product_id' => $item->product_id,
                    'location_id' => $purchase->location_id,
                    'product_variant_id' => $item->product_variant_id,
                ],
                [
                    'qty_on_hand' => 0,
                ]
            );

            $balanceQuery = InventoryBalance::query()
                ->where('tenant_id', $tenantId)
                ->where('product_id', $item->product_id)
                ->where('location_id', $purchase->location_id)
                ->when($item->product_variant_id, function ($query) use ($item) {
                    $query->where('product_variant_id', $item->product_variant_id);
                }, function ($query) {
                    $query->whereNull('product_variant_id');
                });

            $balanceQuery->increment('qty_on_hand', $receiveQty);

            $balanceAfter = Decimal::qty($balanceQuery->value('qty_on_hand'));

            $ledgerRows[] = [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'product_id' => $item->product_id,
                'product_variant_id' => $item->product_variant_id,
                'location_id' => $purchase->location_id,
                'reference_type' => 'purchase',
                'reference_id' => $purchase->id,
                'reference_item_id' => $item->id,
                'qty_in' => $receiveQty,
                'qty_out' => 0,
                'balance_after' => $balanceAfter,
                'unit_cost' => Decimal::float($item->price, Decimal::COST_SCALE),
                'total_cost' => Decimal::float(Decimal::multiply($receiveQty, $item->price, Decimal::MONEY_SCALE), Decimal::MONEY_SCALE),
                'created_at' => $now,
                'created_by' => $userId,
            ];

            $valuationService->receive(
                tenantId: $tenantId,
                productId: $item->product_id,
                productVariantId: $item->product_variant_id,
                locationId: $purchase->location_id,
                qty: $receiveQty,
                unitCost: Decimal::float($item->price, Decimal::COST_SCALE),
                sourceType: 'purchase',
                sourceId: $purchase->id,
                sourceItemId: $item->id,
                at: $now
            );

            $totalReceivedValue = Decimal::add(
                $totalReceivedValue,
                Decimal::multiply($receiveQty, $item->price, Decimal::MONEY_SCALE),
                Decimal::MONEY_SCALE
            );
            $receivedCount++;
        }

        if ($receivedCount === 0) {
            throw new RuntimeException('Tidak ada item yang diterima.');
        }

        StockLedger::query()->insert($ledgerRows);

        $this->createJournalEntries(
            tenantId: $tenantId,
            purchaseId: $purchase->id,
            paymentMethod: (string) ($purchase->payment_method ?? 'tempo'),
            cashAccountId: $purchase->cash_account_id ? (string) $purchase->cash_account_id : null,
            total: Decimal::float($totalReceivedValue, Decimal::MONEY_SCALE),
            userId: $userId,
            now: $now,
            transactionDate: $transactionDate
        );

        $hasRemaining = PurchaseItem::query()
            ->where('tenant_id', $tenantId)
            ->where('purchase_id', $purchase->id)
            ->whereRaw('qty > received_qty')
            ->exists();

        $purchase->update([
            'status' => $hasRemaining ? PurchaseStatus::PARTIALLY_RECEIVED : PurchaseStatus::RECEIVED,
            'received_at' => $now,
            'received_by' => $userId,
            'transaction_date' => $transactionDate->toDateString(),
        ]);

        event(new PurchaseReceived(
            purchaseId: $purchase->id,
            tenantId: $tenantId,
            total: Decimal::float($totalReceivedValue, Decimal::MONEY_SCALE),
            actorId: $userId,
            occurredAt: $now->toIso8601String()
        ));

        return [
            'status' => $purchase->status,
            'received_total' => Decimal::float($totalReceivedValue, Decimal::MONEY_SCALE),
        ];
    }

    private function resolveDefaultCashAccountId(string $tenantId): ?string
    {
        $mapping = AccountMapping::query()
            ->where('tenant_id', $tenantId)
            ->first(['cash_account_id']);

        return $mapping?->cash_account_id ? (string) $mapping->cash_account_id : null;
    }

    private function createJournalEntries(
        string $tenantId,
        string $purchaseId,
        string $paymentMethod,
        ?string $cashAccountId,
        float $total,
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
            'reference_type' => 'purchase',
            'reference_id' => $purchaseId,
            'date' => $transactionDate->toDateString(),
            'description' => 'Purchase received',
            'status' => 'posted',
            'posted_at' => $now,
            'created_by' => $userId,
            'created_at' => $now,
        ]);

        $creditAccountId = $mapping->payable_account_id;
        if (strtolower($paymentMethod) === 'tunai') {
            $creditAccountId = $cashAccountId ?: $mapping->cash_account_id;
        }

        if (! $creditAccountId) {
            throw new RuntimeException('Akun kredit purchase belum ditentukan.');
        }

        $journalItems = [
            [
                'id' => (string) Str::uuid(),
                'journal_entry_id' => $journalEntryId,
                'tenant_id' => $tenantId,
                'account_id' => $mapping->inventory_account_id,
                'debit' => $total,
                'credit' => 0,
                'is_reversal' => false,
            ],
            [
                'id' => (string) Str::uuid(),
                'journal_entry_id' => $journalEntryId,
                'tenant_id' => $tenantId,
                'account_id' => $creditAccountId,
                'debit' => 0,
                'credit' => $total,
                'is_reversal' => false,
            ],
        ];

        JournalItem::query()->insert($journalItems);
    }
}
