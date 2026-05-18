<?php

namespace App\Services;

use App\Events\SaleCompleted;
use App\Models\AccountMapping;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\OnlineOrder;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shift;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;

class OnlineOrderSalesSyncService
{
    public function __construct(
        private readonly OnlineOrderCustomerSyncService $customerSync,
    ) {
    }

    /**
     * @param  array{account_id?:string|null,method?:string|null,payment_method_id?:string|null}  $paymentPayload
     */
    public function ensureSale(OnlineOrder $order, ?string $actorUserId = null, array $paymentPayload = []): array
    {
        $tenantId = (string) $order->tenant_id;
        $idempotencyKey = $this->idempotencyKey($order);

        $freshOrder = OnlineOrder::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $order->id)
            ->with('items')
            ->lockForUpdate()
            ->firstOrFail();

        $existingSaleId = $freshOrder->sale_id ?: Sale::query()
            ->where('tenant_id', $tenantId)
            ->where('idempotency_key', $idempotencyKey)
            ->value('id');

        if ($existingSaleId) {
            if (! $freshOrder->sale_id) {
                $freshOrder->forceFill(['sale_id' => $existingSaleId])->save();
            }

            $customer = $this->customerSync->syncForOrder($freshOrder->refresh());
            if ($customer) {
                Sale::query()
                    ->where('tenant_id', $tenantId)
                    ->where('id', $existingSaleId)
                    ->whereNull('customer_id')
                    ->update(['customer_id' => $customer->id]);
            }

            return [
                'sale_id' => (string) $existingSaleId,
                'sale_item_ids_by_key' => $this->saleItemIdsByKey($tenantId, (string) $existingSaleId),
                'created' => false,
                'transaction_date' => $this->transactionDate($freshOrder),
            ];
        }

        $items = $freshOrder->items;
        if ($items->isEmpty()) {
            throw new InvalidArgumentException('Item pesanan online tidak ditemukan.');
        }

        $branchId = $this->resolveBranchId($freshOrder);
        $now = CarbonImmutable::now('UTC');
        $transactionDate = $this->transactionDate($freshOrder);
        $saleId = (string) Str::uuid();
        $shiftId = $this->resolveShiftId($tenantId, $branchId, $actorUserId, $now);
        $customer = $this->customerSync->syncForOrder($freshOrder);
        $customerId = $customer?->id ?: ($freshOrder->customer_id ? (string) $freshOrder->customer_id : null);
        $saleItems = [];
        $saleItemIdsByKey = [];
        $productIds = $items->pluck('product_id')->filter()->unique()->values()->all();
        $products = Product::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        foreach ($items as $item) {
            $product = $products->get($item->product_id);
            $saleItemId = (string) Str::uuid();
            $variantId = $item->product_variant_id ? (string) $item->product_variant_id : null;

            $saleItems[] = [
                'id' => $saleItemId,
                'tenant_id' => $tenantId,
                'sale_id' => $saleId,
                'product_id' => $item->product_id,
                'product_variant_id' => $variantId,
                'qty' => Decimal::qty($item->qty),
                'price' => Decimal::money($item->price),
                'discount' => 0,
                'hpp_snapshot' => Decimal::cost($product?->hpp_average ?? 0),
            ];

            $saleItemIdsByKey[$this->itemKey((string) $item->product_id, $variantId)] = $saleItemId;
        }

        Sale::query()->create([
            'id' => $saleId,
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'shift_id' => $shiftId,
            'register_id' => null,
            'customer_id' => $customerId,
            'order_holder_name' => $freshOrder->customer_name,
            'order_note' => $this->buildSaleNote($freshOrder),
            'subtotal' => Decimal::float($this->saleSubtotal($freshOrder), Decimal::MONEY_SCALE),
            'discount' => Decimal::float(Decimal::money($freshOrder->shipping_discount), Decimal::MONEY_SCALE),
            'tax' => 0,
            'total' => Decimal::float(Decimal::money($freshOrder->grand_total), Decimal::MONEY_SCALE),
            'status' => Sale::STATUS_COMPLETED,
            'idempotency_key' => $idempotencyKey,
            'created_by' => $actorUserId,
            'voided_by' => null,
            'created_at' => $now,
            'transaction_date' => $transactionDate,
        ]);

        SaleItem::query()->insert($saleItems);

        $this->ensurePayment($tenantId, $saleId, Decimal::float($freshOrder->grand_total, Decimal::MONEY_SCALE), $paymentPayload);

        $freshOrder->forceFill(['sale_id' => $saleId])->save();

        return [
            'sale_id' => $saleId,
            'sale_item_ids_by_key' => $saleItemIdsByKey,
            'created' => true,
            'transaction_date' => $transactionDate,
        ];
    }

    public function finalizeSale(
        string $tenantId,
        string $saleId,
        string $transactionDate,
        ?string $actorUserId,
        bool $dispatchEvent
    ): void {
        $now = CarbonImmutable::now('UTC');
        $costAggregate = app(SalesCostAggregationService::class)->captureForSale(
            tenantId: $tenantId,
            saleId: $saleId,
            now: $now,
            source: 'online_order'
        );

        $this->ensureJournal(
            tenantId: $tenantId,
            saleId: $saleId,
            cogsTotal: (float) $costAggregate['total_cogs'],
            actorUserId: $actorUserId,
            transactionDate: $transactionDate,
            now: $now
        );

        $this->forgetDashboardCache($tenantId, $transactionDate);

        if ($dispatchEvent) {
            event(new SaleCompleted(
                saleId: $saleId,
                tenantId: $tenantId,
                total: (float) Sale::query()
                    ->where('tenant_id', $tenantId)
                    ->where('id', $saleId)
                    ->value('total'),
                actorId: $actorUserId,
                occurredAt: $now->toIso8601String()
            ));
        }
    }

    public function saleItemIdsByKey(string $tenantId, string $saleId): array
    {
        return SaleItem::query()
            ->where('tenant_id', $tenantId)
            ->where('sale_id', $saleId)
            ->get(['id', 'product_id', 'product_variant_id'])
            ->mapWithKeys(fn (SaleItem $item): array => [
                $this->itemKey((string) $item->product_id, $item->product_variant_id ? (string) $item->product_variant_id : null) => (string) $item->id,
            ])
            ->all();
    }

    public function itemKey(string $productId, ?string $productVariantId): string
    {
        return $productId.'|'.($productVariantId ?: 'base');
    }

    private function resolveBranchId(OnlineOrder $order): string
    {
        $branchId = (string) ($order->branch_id ?: '');

        if ($branchId !== '') {
            return $branchId;
        }

        if ($order->location_id) {
            $branchId = (string) DB::table('locations')
                ->where('tenant_id', $order->tenant_id)
                ->where('id', $order->location_id)
                ->value('branch_id');
        }

        if ($branchId !== '') {
            return $branchId;
        }

        $branchId = (string) DB::table('branches')
            ->where('tenant_id', $order->tenant_id)
            ->orderBy('name')
            ->value('id');

        if ($branchId === '') {
            throw new InvalidArgumentException('Cabang untuk pesanan online tidak ditemukan.');
        }

        return $branchId;
    }

    private function resolveShiftId(string $tenantId, string $branchId, ?string $actorUserId, CarbonImmutable $now): string
    {
        $openShiftId = Shift::query()
            ->where('tenant_id', $tenantId)
            ->where('branch_id', $branchId)
            ->where('status', 'open')
            ->orderByDesc('opened_at')
            ->value('id');

        if ($openShiftId) {
            return (string) $openShiftId;
        }

        $userId = $actorUserId ?: (string) DB::table('users')
            ->where('tenant_id', $tenantId)
            ->orderBy('created_at')
            ->value('id');

        if ($userId === '') {
            throw new InvalidArgumentException('User pencatat order online tidak ditemukan.');
        }

        $shiftId = (string) Str::uuid();
        Shift::query()->create([
            'id' => $shiftId,
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'user_id' => $userId,
            'opened_at' => $now,
            'closed_at' => $now,
            'opening_cash' => 0,
            'closing_cash' => 0,
            'system_cash_total' => 0,
            'difference' => 0,
            'status' => 'closed',
        ]);

        return $shiftId;
    }

    /**
     * @param  array{account_id?:string|null,method?:string|null,payment_method_id?:string|null}  $paymentPayload
     */
    private function ensurePayment(string $tenantId, string $saleId, float $total, array $paymentPayload = []): void
    {
        if ($total <= 0 || Payment::query()->where('tenant_id', $tenantId)->where('sale_id', $saleId)->exists()) {
            return;
        }

        $mapping = AccountMapping::query()->where('tenant_id', $tenantId)->first();
        $method = in_array(($paymentPayload['method'] ?? null), ['cash', 'bank', 'qris'], true)
            ? (string) $paymentPayload['method']
            : ($mapping?->bank_account_id ? 'bank' : 'cash');
        $accountId = $paymentPayload['account_id'] ?? null;
        $accountId = $accountId ?: ($method === 'cash' ? $mapping?->cash_account_id : $mapping?->bank_account_id);
        $accountId = $accountId ?: $mapping?->cash_account_id;

        if (! $accountId) {
            return;
        }

        $row = [
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'sale_id' => $saleId,
            'account_id' => $accountId,
            'method' => $method,
            'amount' => $total,
        ];

        if (Schema::hasColumn('payments', 'payment_method_id')) {
            $row['payment_method_id'] = $paymentPayload['payment_method_id'] ?? null;
        }

        Payment::query()->create($row);
    }

    private function ensureJournal(
        string $tenantId,
        string $saleId,
        float $cogsTotal,
        ?string $actorUserId,
        string $transactionDate,
        CarbonImmutable $now
    ): void {
        if (JournalEntry::query()
            ->where('tenant_id', $tenantId)
            ->where('reference_type', 'sale')
            ->where('reference_id', $saleId)
            ->exists()) {
            app(SalesCostAggregationService::class)->syncSaleJournalForSale($tenantId, $saleId);

            return;
        }

        $mapping = AccountMapping::query()->where('tenant_id', $tenantId)->first();
        if (! $mapping) {
            return;
        }

        $payments = Payment::query()
            ->where('tenant_id', $tenantId)
            ->where('sale_id', $saleId)
            ->get(['account_id', 'amount']);
        $saleTotal = (float) Sale::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $saleId)
            ->value('total');

        $journalEntryId = (string) Str::uuid();
        JournalEntry::query()->create([
            'id' => $journalEntryId,
            'tenant_id' => $tenantId,
            'reference_type' => 'sale',
            'reference_id' => $saleId,
            'date' => $transactionDate,
            'description' => 'Online order sale',
            'status' => 'posted',
            'posted_at' => $now,
            'created_by' => $actorUserId,
            'created_at' => $now,
        ]);

        $journalItems = [];
        foreach ($payments->groupBy('account_id') as $accountId => $accountPayments) {
            $amount = $accountPayments->sum(fn (Payment $payment): float => (float) $payment->amount);
            if ($accountId && $amount > 0) {
                $journalItems[] = $this->journalItem($journalEntryId, $tenantId, (string) $accountId, $amount, 0);
            }
        }

        $journalItems[] = $this->journalItem($journalEntryId, $tenantId, $mapping->sale_account_id, 0, $saleTotal);

        if ($cogsTotal > 0) {
            $journalItems[] = $this->journalItem($journalEntryId, $tenantId, $mapping->cogs_account_id, $cogsTotal, 0);
            $journalItems[] = $this->journalItem($journalEntryId, $tenantId, $mapping->inventory_account_id, 0, $cogsTotal);
        }

        JournalItem::query()->insert($journalItems);
    }

    private function journalItem(string $journalEntryId, string $tenantId, string $accountId, float $debit, float $credit): array
    {
        return [
            'id' => (string) Str::uuid(),
            'journal_entry_id' => $journalEntryId,
            'tenant_id' => $tenantId,
            'account_id' => $accountId,
            'debit' => $debit,
            'credit' => $credit,
            'is_reversal' => false,
        ];
    }

    private function saleSubtotal(OnlineOrder $order): string
    {
        return Decimal::add(
            Decimal::add($order->subtotal, $order->shipping_cost, Decimal::MONEY_SCALE),
            $order->extra_fee,
            Decimal::MONEY_SCALE
        );
    }

    private function transactionDate(OnlineOrder $order): string
    {
        $at = $order->payment_verified_at
            ? CarbonImmutable::parse($order->payment_verified_at)
            : CarbonImmutable::now('UTC');

        return $at->setTimezone('Asia/Jakarta')->toDateString();
    }

    private function buildSaleNote(OnlineOrder $order): string
    {
        $lines = [
            'Order online '.$order->order_code,
            'Metode: '.($order->fulfillment_type === 'delivery' ? 'Antar ke alamat' : 'Ambil di toko'),
        ];

        if ($order->fulfillment_time) {
            $lines[] = 'Waktu: '.$order->fulfillment_time;
        }

        if ($order->shipping_provider) {
            $lines[] = 'Ongkir: '.$order->shipping_provider;
        }

        return implode("\n", $lines);
    }

    private function idempotencyKey(OnlineOrder $order): string
    {
        return 'online-order:'.$order->id;
    }

    private function forgetDashboardCache(string $tenantId, string $transactionDate): void
    {
        Cache::forget("pos-dashboard:summary:{$tenantId}:{$transactionDate}");

        $date = CarbonImmutable::parse($transactionDate, 'Asia/Jakarta');
        $monthStart = $date->startOfMonth()->toDateString();
        $monthEnd = $date->endOfMonth()->toDateString();

        for ($limit = 1; $limit <= 10; $limit++) {
            Cache::forget("pos-dashboard:top-products:{$tenantId}:{$monthStart}:{$monthEnd}:{$limit}");
            Cache::forget("pos-dashboard:low-stock:{$tenantId}:{$limit}");
        }
    }
}
