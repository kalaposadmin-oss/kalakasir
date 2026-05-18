<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PosSavedOrder;
use App\Services\DatabaseTransactionService;
use App\Services\PosSavedOrderInventoryService;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PosSavedOrderController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json(['message' => 'Tenant tidak ditemukan.'], 400);
        }

        $limit = max(1, min((int) $request->query('limit', 50), 100));

        $orders = PosSavedOrder::query()
            ->where('tenant_id', $tenantId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        return response()->json([
            'saved_orders' => $orders->map(fn (PosSavedOrder $order) => $this->transform($order))->values(),
            'total' => $orders->count(),
        ]);
    }

    public function store(Request $request)
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json(['message' => 'Tenant tidak ditemukan.'], 400);
        }

        $payload = $this->validatePayload($request, $tenantId);

        $order = app(DatabaseTransactionService::class)->run(function () use ($payload, $tenantId, $request) {
            $order = $this->persistOrder($payload, $request->user()?->id, $tenantId);
            app(PosSavedOrderInventoryService::class)->reserve(
                order: $order,
                items: $payload['items'],
                userId: $request->user()?->id,
                now: CarbonImmutable::now('UTC')
            );

            return $order;
        });

        return response()->json([
            'saved_order' => $this->transform($order),
        ], 201);
    }

    public function update(Request $request, string $savedOrder)
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json(['message' => 'Tenant tidak ditemukan.'], 400);
        }

        $payload = $this->validatePayload($request, $tenantId);

        $order = PosSavedOrder::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $savedOrder)
            ->firstOrFail();

        $previousItems = (array) (($order->payload ?? [])['items'] ?? []);

        $order = app(DatabaseTransactionService::class)->run(function () use ($payload, $tenantId, $request, $order, $previousItems) {
            app(PosSavedOrderInventoryService::class)->release(
                order: $order,
                items: $previousItems,
                userId: $request->user()?->id,
                now: CarbonImmutable::now('UTC')
            );

            $updated = $this->persistOrder($payload, $request->user()?->id, $tenantId, $order);

            app(PosSavedOrderInventoryService::class)->reserve(
                order: $updated,
                items: $payload['items'],
                userId: $request->user()?->id,
                now: CarbonImmutable::now('UTC')
            );

            return $updated;
        });

        return response()->json([
            'saved_order' => $this->transform($order),
        ]);
    }

    public function destroy(string $savedOrder)
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json(['message' => 'Tenant tidak ditemukan.'], 400);
        }

        $order = PosSavedOrder::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $savedOrder)
            ->firstOrFail();

        app(DatabaseTransactionService::class)->run(function () use ($order) {
            $previousItems = (array) (($order->payload ?? [])['items'] ?? []);
            app(PosSavedOrderInventoryService::class)->release(
                order: $order,
                items: $previousItems,
                userId: null,
                now: CarbonImmutable::now('UTC')
            );
            $order->delete();
        });

        return response()->noContent();
    }

    private function validatePayload(Request $request, string $tenantId): array
    {
        return $request->validate([
            'branch_id' => ['nullable', 'uuid', Rule::exists('branches', 'id')->where('tenant_id', $tenantId)],
            'location_id' => ['nullable', 'uuid', Rule::exists('locations', 'id')->where('tenant_id', $tenantId)],
            'shift_id' => ['nullable', 'uuid'],
            'register_id' => ['nullable', 'uuid', Rule::exists('registers', 'id')->where('tenant_id', $tenantId)],
            'customer_id' => ['nullable', 'uuid', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)],
            'holder_name' => ['required', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'uuid'],
            'items.*.qty' => ['required', 'numeric', 'min:0.01'],
            'items.*.discounts' => ['nullable', 'array'],
            'items.*.discounts.*.type' => ['required_with:items.*.discounts', 'string', 'in:fixed,percent'],
            'items.*.discounts.*.value' => ['required_with:items.*.discounts', 'numeric', 'min:0'],
            'items.*.discounts.*.label' => ['nullable', 'string', 'max:120'],
            'items.*.discounts.*.source' => ['nullable', 'string', 'max:60'],
            'items.*.discounts.*.reference_id' => ['nullable', 'string', 'max:120'],
            'items.*.note' => ['nullable', 'string', 'max:500'],
            'fees' => ['nullable', 'array'],
            'selected_promos' => ['nullable', 'array'],
            'transaction_discounts' => ['nullable', 'array'],
            'total' => ['required', 'numeric', 'min:0'],
            'item_count' => ['required', 'integer', 'min:1'],
        ]);
    }

    private function persistOrder(array $payload, ?string $createdBy, string $tenantId, ?PosSavedOrder $order = null): PosSavedOrder
    {
        $attributes = [
            'tenant_id' => $tenantId,
            'branch_id' => $payload['branch_id'] ?? null,
            'location_id' => $payload['location_id'] ?? null,
            'shift_id' => $payload['shift_id'] ?? null,
            'register_id' => $payload['register_id'] ?? null,
            'customer_id' => $payload['customer_id'] ?? null,
            'holder_name' => trim($payload['holder_name']),
            'note' => isset($payload['note']) ? trim((string) $payload['note']) : null,
            'payload' => [
                'items' => $this->normalizeItems($payload['items']),
                'fees' => $payload['fees'] ?? [],
                'selected_promos' => $payload['selected_promos'] ?? [],
                'transaction_discounts' => $payload['transaction_discounts'] ?? [],
            ],
            'total' => (float) $payload['total'],
            'item_count' => (int) $payload['item_count'],
            'created_by' => $createdBy,
            'created_at' => now('UTC'),
        ];

        if ($order) {
            $order->fill($attributes);
            $order->save();

            return $order;
        }

        return PosSavedOrder::query()->create($attributes);
    }

    private function normalizeItems(array $items): array
    {
        return array_values(array_map(function (array $item): array {
            $normalizedNote = isset($item['note']) ? trim((string) $item['note']) : null;

            return [
                'product_id' => $item['product_id'],
                'qty' => $item['qty'],
                'discounts' => array_values((array) ($item['discounts'] ?? [])),
                'note' => $normalizedNote !== '' ? $normalizedNote : null,
            ];
        }, $items));
    }

    private function transform(PosSavedOrder $order): array
    {
        $payload = (array) ($order->payload ?? []);

        return [
            'id' => $order->id,
            'holder_name' => (string) ($order->holder_name ?? ''),
            'note' => (string) ($order->note ?? ''),
            'items' => array_values((array) ($payload['items'] ?? [])),
            'fees' => array_values((array) ($payload['fees'] ?? [])),
            'selected_promos' => array_values((array) ($payload['selected_promos'] ?? [])),
            'transaction_discounts' => array_values((array) ($payload['transaction_discounts'] ?? [])),
            'customer_id' => $order->customer_id,
            'branch_id' => $order->branch_id,
            'location_id' => $order->location_id,
            'shift_id' => $order->shift_id,
            'register_id' => $order->register_id,
            'total' => (float) ($order->total ?? 0),
            'item_count' => (int) ($order->item_count ?? 0),
            'created_at' => optional($order->created_at)?->toIso8601String(),
        ];
    }
}
