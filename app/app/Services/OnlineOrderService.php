<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\OnlineOrder;
use App\Models\OnlineOrderItem;
use App\Models\PaymentSetting;
use App\Models\Product;
use App\Models\Tenant;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class OnlineOrderService
{
    public function __construct(
        private readonly StockAvailabilityService $stockAvailability,
        private readonly OrderReservationService $reservationService,
        private readonly WhatsAppMessageService $whatsAppMessageService,
        private readonly OnlineOrderSalesSyncService $salesSyncService,
        private readonly OnlineOrderCustomerSyncService $customerSync,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{order: OnlineOrder, orderItems: Collection<int, OnlineOrderItem>, adminPhone: string|null, adminMessage: string, customerMessage: string|null}
     */
    public function createOrder(Tenant $tenant, array $payload): array
    {
        $tenantId = $tenant->id;
        $locationId = $this->stockAvailability->resolveDefaultLocationId($tenant);
        if (! $locationId) {
            throw new InvalidArgumentException('Lokasi default tenant belum tersedia.');
        }

        $items = collect($payload['items'] ?? [])
            ->values()
            ->filter(fn ($item) => is_array($item))
            ->all();

        if ($items === []) {
            throw new InvalidArgumentException('Keranjang masih kosong.');
        }

        $productIds = collect($items)->pluck('product_id')->filter()->unique()->values()->all();
        $products = Product::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        $orderCode = $this->generateOrderCode($tenantId);
        $adminToken = Str::random(40);
        $now = CarbonImmutable::now('UTC');

        return DB::transaction(function () use ($tenant, $tenantId, $locationId, $payload, $items, $products, $orderCode, $adminToken, $now) {
            $customer = $this->resolveOrderCustomer($tenantId, $payload);

            $order = OnlineOrder::query()->create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'branch_id' => $tenant->default_location_id ? DB::table('locations')->where('id', $tenant->default_location_id)->value('branch_id') : null,
                'location_id' => $locationId,
                'customer_id' => $customer?->id,
                'order_code' => $orderCode,
                'customer_name' => trim((string) ($payload['customer_name'] ?? '')),
                'customer_phone' => trim((string) ($payload['customer_phone'] ?? '')),
                'fulfillment_type' => $this->normalizeFulfillmentType((string) ($payload['fulfillment_type'] ?? 'pickup')),
                'fulfillment_time' => trim((string) ($payload['fulfillment_time'] ?? '')) ?: null,
                'address' => trim((string) ($payload['address'] ?? '')) ?: null,
                'customer_note' => trim((string) ($payload['customer_note'] ?? '')) ?: null,
                'subtotal' => 0,
                'shipping_provider' => null,
                'shipping_cost' => 0,
                'shipping_discount' => 0,
                'extra_fee' => 0,
                'grand_total' => 0,
                'status' => 'menunggu_ongkir',
                'admin_token' => $adminToken,
                'token_expires_at' => $now->addHours(max(1, (int) config('services.order_online.hold_ttl_hours', 24))),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $subtotal = '0';
            $orderItems = collect();

            foreach ($items as $item) {
                $productId = (string) Arr::get($item, 'product_id', '');
                $qty = (int) Arr::get($item, 'qty', 0);
                $productVariantId = Arr::get($item, 'product_variant_id');

                if ($productId === '' || $qty <= 0) {
                    continue;
                }

                $product = $products->get($productId);
                if (! $product) {
                    throw new InvalidArgumentException('Produk pesanan tidak ditemukan.');
                }

                $price = $this->resolveItemPrice($product, $productVariantId ? (string) $productVariantId : null);
                $itemSubtotal = Decimal::multiply($price, (string) $qty, Decimal::MONEY_SCALE);
                $subtotal = Decimal::add($subtotal, $itemSubtotal, Decimal::MONEY_SCALE);

                $orderItem = OnlineOrderItem::query()->create([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'online_order_id' => $order->id,
                    'product_id' => $productId,
                    'product_variant_id' => $productVariantId ?: null,
                    'product_name' => (string) $product->name,
                    'product_sku' => $product->sku ?? null,
                    'price' => $price,
                    'qty' => $qty,
                    'subtotal' => $itemSubtotal,
                    'meta' => [
                        'source' => 'public_catalog',
                    ],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $orderItems->push($orderItem);
            }

            if ($orderItems->isEmpty()) {
                throw new InvalidArgumentException('Keranjang masih kosong.');
            }

            $order->forceFill([
                'subtotal' => $subtotal,
                'grand_total' => $subtotal,
            ])->save();

            $this->reservationService->reserve($order, $orderItems->map(fn (OnlineOrderItem $item) => [
                'product_id' => $item->product_id,
                'qty' => $item->qty,
                'product_variant_id' => $item->product_variant_id,
            ])->all());

            $paymentSetting = PaymentSetting::query()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->orderByDesc('created_at')
                ->first();

            $adminMessage = $this->whatsAppMessageService->buildAdminOrderMessage($order, $orderItems, $paymentSetting);
            $customerMessage = null;
            $adminPhone = $paymentSetting?->admin_whatsapp_number ?: (string) config('services.order_online.admin_whatsapp_number', '');

            return [
                'order' => $order,
                'orderItems' => $orderItems,
                'adminPhone' => $adminPhone !== '' ? $adminPhone : null,
                'adminMessage' => $adminMessage,
                'customerMessage' => $customerMessage,
            ];
        });
    }

    public function findByCode(string $tenantId, string $orderCode): ?OnlineOrder
    {
        return OnlineOrder::query()
            ->where('tenant_id', $tenantId)
            ->where('order_code', $orderCode)
            ->first();
    }

    public function findByCodeAndToken(string $tenantId, string $orderCode, string $token): ?OnlineOrder
    {
        return OnlineOrder::query()
            ->where('tenant_id', $tenantId)
            ->where('order_code', $orderCode)
            ->where('admin_token', $token)
            ->first();
    }

    public function syncCustomer(OnlineOrder $order): ?string
    {
        return $this->customerSync->syncForOrder($order)?->id;
    }

    private function resolveOrderCustomer(string $tenantId, array $payload): ?Customer
    {
        $customerId = trim((string) ($payload['customer_id'] ?? ''));
        $name = trim((string) ($payload['customer_name'] ?? ''));
        $phone = trim((string) ($payload['customer_phone'] ?? ''));
        $address = trim((string) ($payload['address'] ?? ''));

        if ($customerId !== '') {
            $customer = Customer::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($customerId)
                ->first();

            if ($customer) {
                $changes = [];

                if ($name !== '' && $customer->name !== $name) {
                    $changes['name'] = $name;
                }

                if ($phone !== '' && $customer->phone !== $phone) {
                    $changes['phone'] = $phone;
                }

                if ($address !== '' && $customer->address !== $address) {
                    $changes['address'] = $address;
                }

                if ($changes !== []) {
                    $customer->forceFill($changes)->save();
                }

                return $customer->refresh();
            }
        }

        return $this->customerSync->syncFromPayload(
            tenantId: $tenantId,
            name: $payload['customer_name'] ?? null,
            phone: $payload['customer_phone'] ?? null,
            address: $payload['address'] ?? null,
        );
    }

    public function resolvePaymentSetting(string $tenantId): ?PaymentSetting
    {
        return PaymentSetting::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderByDesc('created_at')
            ->first();
    }

    public function buildCustomerPaymentMessage(OnlineOrder $order): string
    {
        return $this->whatsAppMessageService->buildCustomerPaymentMessage($order, $this->resolvePaymentSetting($order->tenant_id));
    }

    public function buildAdminMessage(OnlineOrder $order): string
    {
        return $this->whatsAppMessageService->buildAdminOrderMessage($order, $order->items ?? collect(), $this->resolvePaymentSetting($order->tenant_id));
    }

    public function adminWaLink(OnlineOrder $order): ?string
    {
        $phone = $this->resolveAdminPhone($order);

        return $this->whatsAppMessageService->buildLink($phone, $this->buildAdminMessage($order));
    }

    public function customerWaLink(OnlineOrder $order): ?string
    {
        $paymentSetting = $this->resolvePaymentSetting($order->tenant_id);
        $phone = $order->customer_phone;

        return $this->whatsAppMessageService->buildLink($phone, $this->whatsAppMessageService->buildCustomerPaymentMessage($order, $paymentSetting));
    }

    private function resolveAdminPhone(OnlineOrder $order): ?string
    {
        $paymentSetting = $this->resolvePaymentSetting($order->tenant_id);
        $paymentPhone = trim((string) ($paymentSetting?->admin_whatsapp_number ?? ''));
        if ($paymentPhone !== '') {
            return $paymentPhone;
        }

        $branchPhone = $order->branch_id
            ? DB::table('branches')->where('id', $order->branch_id)->value('phone')
            : null;

        $branchPhone = trim((string) $branchPhone);
        if ($branchPhone !== '') {
            return $branchPhone;
        }

        $configPhone = trim((string) config('services.order_online.admin_whatsapp_number', ''));

        return $configPhone !== '' ? $configPhone : null;
    }

    public function markShippingFilled(OnlineOrder $order, array $payload, ?string $actorUserId = null): OnlineOrder
    {
        $order->forceFill([
            'shipping_provider' => trim((string) ($payload['shipping_provider'] ?? '')) ?: null,
            'shipping_cost' => Decimal::money($payload['shipping_cost'] ?? 0),
            'shipping_discount' => Decimal::money($payload['shipping_discount'] ?? 0),
            'extra_fee' => Decimal::money($payload['extra_fee'] ?? 0),
            'customer_note' => trim((string) ($payload['customer_note'] ?? $order->customer_note ?? '')) ?: null,
            'grand_total' => Decimal::add(
                Decimal::add($order->subtotal, $payload['shipping_cost'] ?? 0, Decimal::MONEY_SCALE),
                Decimal::subtract($payload['extra_fee'] ?? 0, $payload['shipping_discount'] ?? 0, Decimal::MONEY_SCALE),
                Decimal::MONEY_SCALE
            ),
            'status' => 'menunggu_pembayaran',
            'shipping_filled_at' => now('UTC'),
        ])->save();

        $this->customerSync->syncForOrder($order->refresh());

        return $order->refresh();
    }

    public function markPaymentMessageSent(OnlineOrder $order): void
    {
        $order->forceFill([
            'payment_message_sent_at' => now('UTC'),
            'status' => 'menunggu_pembayaran',
        ])->save();
    }

    /**
     * @param  array{account_id?:string|null,method?:string|null,payment_method_id?:string|null}  $paymentPayload
     */
    public function markPaid(OnlineOrder $order, ?string $actorUserId = null, array $paymentPayload = []): OnlineOrder
    {
        return DB::transaction(function () use ($order, $actorUserId, $paymentPayload): OnlineOrder {
            $sync = $this->salesSyncService->ensureSale($order, $actorUserId, $paymentPayload);

            $this->reservationService->convert(
                $order,
                $actorUserId,
                $sync['sale_id'],
                $sync['sale_item_ids_by_key']
            );

            $freshOrder = OnlineOrder::query()
                ->where('tenant_id', $order->tenant_id)
                ->where('id', $order->id)
                ->lockForUpdate()
                ->firstOrFail();

            $freshOrder->forceFill([
                'sale_id' => $sync['sale_id'],
                'payment_verified_at' => $freshOrder->payment_verified_at ?: now('UTC'),
                'status' => 'pembayaran_terverifikasi',
            ])->save();

            $this->salesSyncService->finalizeSale(
                tenantId: (string) $freshOrder->tenant_id,
                saleId: (string) $sync['sale_id'],
                transactionDate: (string) $sync['transaction_date'],
                actorUserId: $actorUserId,
                dispatchEvent: (bool) $sync['created']
            );

            return $freshOrder->refresh();
        });
    }

    public function cancel(OnlineOrder $order): OnlineOrder
    {
        $this->reservationService->releaseByOrder($order);

        $order->forceFill([
            'status' => 'dibatalkan',
            'cancelled_at' => now('UTC'),
        ])->save();

        return $order->refresh();
    }

    public function complete(OnlineOrder $order, ?string $actorUserId = null): OnlineOrder
    {
        if ($order->status === 'dibatalkan') {
            return $order->refresh();
        }

        $order->forceFill([
            'status' => 'selesai',
            'completed_at' => $order->completed_at ?: now('UTC'),
        ])->save();

        return $order->refresh();
    }

    /**
     * @param  object|array<string, mixed>  $product
     */
    private function resolveItemPrice(object|array $product, ?string $productVariantId): string
    {
        $price = data_get($product, 'price', 0);

        if ($productVariantId === null || $productVariantId === '') {
            return Decimal::money($price);
        }

        $variant = DB::table('product_variants')
            ->where('id', $productVariantId)
            ->where('product_id', data_get($product, 'id'))
            ->first(['price_override']);

        if ($variant && $variant->price_override !== null) {
            return Decimal::money($variant->price_override);
        }

        return Decimal::money($price);
    }

    private function generateOrderCode(string $tenantId): string
    {
        do {
            $code = 'LV-'.random_int(10000, 99999);
        } while (OnlineOrder::query()->where('tenant_id', $tenantId)->where('order_code', $code)->exists());

        return $code;
    }

    private function normalizeFulfillmentType(string $value): string
    {
        $value = strtolower(trim($value));

        return in_array($value, ['pickup', 'delivery'], true) ? $value : 'pickup';
    }
}
