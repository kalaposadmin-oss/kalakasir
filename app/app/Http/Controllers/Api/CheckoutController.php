<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InsufficientStockException;
use App\Exceptions\LockPeriodException;
use App\Exceptions\InvalidTransactionDateException;
use App\Exceptions\AutoConsumeValidationException;
use App\Http\Controllers\Controller;
use App\Exceptions\ShiftValidationException;
use App\Services\AnomalyService;
use App\Services\CheckoutService;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class CheckoutController extends Controller
{
    public function __construct(private readonly CheckoutService $service)
    {
    }

    public function store(Request $request)
    {
        $startedAt = microtime(true);
        $tenantId = TenantContext::id();

        $validated = $request->validate([
            'idempotency_key' => ['required', 'string', 'max:255'],
            'branch_id' => ['required', 'uuid', $tenantId
                ? Rule::exists('branches', 'id')->where('tenant_id', $tenantId)
                : 'exists:branches,id'],
            'shift_id' => ['required', 'uuid', $tenantId
                ? Rule::exists('shifts', 'id')->where('tenant_id', $tenantId)
                : 'exists:shifts,id'],
            'register_id' => ['required', 'uuid', $tenantId
                ? Rule::exists('registers', 'id')->where('tenant_id', $tenantId)
                : 'exists:registers,id'],
            'customer_id' => ['nullable', 'uuid', $tenantId
                ? Rule::exists('customers', 'id')->where('tenant_id', $tenantId)
                : 'exists:customers,id'],
            'order_holder_name' => ['nullable', 'string', 'max:120'],
            'order_note' => ['nullable', 'string', 'max:500'],
            'transaction_date' => ['nullable', 'date'],
            'replace_sale_id' => ['nullable', 'uuid', $tenantId
                ? Rule::exists('sales', 'id')->where('tenant_id', $tenantId)
                : 'exists:sales,id'],
            'saved_order_id' => ['nullable', 'uuid', $tenantId
                ? Rule::exists('pos_saved_orders', 'id')->where('tenant_id', $tenantId)
                : 'exists:pos_saved_orders,id'],
            'payment_mode' => ['nullable', 'string', Rule::in(['full', 'partial', 'receivable'])],
            'due_date' => ['nullable', 'date'],
            'subtotal' => ['required', 'numeric', 'min:0'],
            'discount' => ['required', 'numeric', 'min:0'],
            'tax' => ['required', 'numeric', 'min:0'],
            'total' => ['required', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'uuid', $tenantId
                ? Rule::exists('products', 'id')->where('tenant_id', $tenantId)
                : 'exists:products,id'],
            'items.*.product_variant_id' => ['nullable', 'uuid'],
            'items.*.location_id' => ['required', 'uuid', $tenantId
                ? Rule::exists('locations', 'id')->where('tenant_id', $tenantId)
                : 'exists:locations,id'],
            'items.*.qty' => ['required', 'numeric', 'min:0.01'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'items.*.line_type' => ['nullable', 'string', 'in:product,fee'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'items.*.discounts' => ['nullable', 'array'],
            'items.*.discounts.*.type' => ['required_with:items.*.discounts', 'string', 'in:fixed,percent'],
            'items.*.discounts.*.value' => ['required_with:items.*.discounts', 'numeric', 'min:0'],
            'items.*.discounts.*.label' => ['nullable', 'string', 'max:120'],
            'items.*.discounts.*.source' => ['nullable', 'string', 'max:60'],
            'items.*.discounts.*.reference_id' => ['nullable', 'string', 'max:120'],
            'transaction_discounts' => ['nullable', 'array'],
            'transaction_discounts.*.type' => ['required_with:transaction_discounts', 'string', 'in:fixed,percent'],
            'transaction_discounts.*.value' => ['required_with:transaction_discounts', 'numeric', 'min:0'],
            'transaction_discounts.*.label' => ['nullable', 'string', 'max:120'],
            'transaction_discounts.*.source' => ['nullable', 'string', 'max:60'],
            'transaction_discounts.*.reference_id' => ['nullable', 'string', 'max:120'],
            'payments' => ['nullable', 'array'],
            'payments.*.account_id' => ['required', 'uuid', $tenantId
                ? Rule::exists('chart_of_accounts', 'id')->where('tenant_id', $tenantId)
                : 'exists:chart_of_accounts,id'],
            'payments.*.method' => ['required', 'string', 'max:50'],
            'payments.*.amount' => ['required', 'numeric', 'min:0.01'],
            'payments.*.payment_method_id' => ['nullable', 'uuid', $tenantId
                ? Rule::exists('payment_methods', 'id')->where('tenant_id', $tenantId)
                : 'exists:payment_methods,id'],
        ]);
        $validated['payments'] = $validated['payments'] ?? [];

        try {
            $result = $this->service->handle($validated, $this->resolveActorId($request, $validated));

            return $this->timedJsonResponse([
                'sale_id' => $result['sale_id'],
                'idempotent' => $result['idempotent'],
                'receivable_id' => $result['receivable_id'] ?? null,
                'payment_status' => $result['payment_status'] ?? 'paid',
            ], $result['idempotent'] ? 200 : 201, $startedAt, [
                'X-Checkout-Idempotent' => $result['idempotent'] ? '1' : '0',
            ]);
        } catch (LockPeriodException $exception) {
            return $this->timedJsonResponse([
                'code' => $exception->errorCode(),
                'message' => $exception->getMessage(),
                'details' => [],
            ], $exception->status(), $startedAt);
        } catch (InsufficientStockException $exception) {
            return $this->timedJsonResponse([
                'code' => 'INSUFFICIENT_STOCK',
                'message' => $exception->getMessage(),
                'details' => $exception->context(),
            ], 409, $startedAt);
        } catch (ShiftValidationException $exception) {
            return $this->timedJsonResponse([
                'code' => $exception->errorCode(),
                'message' => $exception->getMessage(),
                'details' => [],
            ], $exception->status(), $startedAt);
        } catch (InvalidTransactionDateException $exception) {
            $tenantId = TenantContext::id();
            if ($tenantId) {
                app(AnomalyService::class)->raise(
                    tenantId: $tenantId,
                    severity: 'low',
                    type: 'invalid_transaction_date_attempt',
                    payload: [
                        'source' => 'checkout',
                        'message' => $exception->getMessage(),
                    ]
                );
            }

            return $this->timedJsonResponse([
                'code' => 'INVALID_TRANSACTION_DATE',
                'message' => $exception->getMessage(),
                'details' => [],
            ], 422, $startedAt);
        } catch (AutoConsumeValidationException $exception) {
            return $this->timedJsonResponse([
                'code' => $exception->errorCode(),
                'message' => $exception->getMessage(),
                'details' => $exception->details(),
            ], $exception->status(), $startedAt);
        } catch (InvalidArgumentException $exception) {
            $message = $exception->getMessage();
            $code = match (true) {
                str_contains($message, 'raw material') => 'INVALID_PRODUCT_TYPE',
                str_contains($message, 'piutang'), str_contains($message, 'pembayaran'), str_contains($message, 'Customer') => 'INVALID_PAYMENT',
                default => 'INVALID_TOTAL',
            };

            return $this->timedJsonResponse([
                'code' => $code,
                'message' => $message,
                'details' => [],
            ], 422, $startedAt);
        }
    }

    private function resolveActorId(Request $request, array $validated): ?string
    {
        return $request->user()?->id;
    }

    private function timedJsonResponse(array $payload, int $status, float $startedAt, array $headers = [])
    {
        $headers['Server-Timing'] = 'checkout;dur='.number_format((microtime(true) - $startedAt) * 1000, 2, '.', '');

        return response()->json($payload, $status, $headers);
    }
}
