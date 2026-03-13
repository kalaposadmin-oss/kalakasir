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
use App\Models\Shift;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CheckoutController extends Controller
{
    public function __construct(private readonly CheckoutService $service)
    {
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'idempotency_key' => ['required', 'string', 'max:255'],
            'branch_id' => ['required', 'uuid'],
            'shift_id' => ['required', 'uuid'],
            'register_id' => ['required', 'uuid'],
            'customer_id' => ['nullable', 'uuid'],
            'transaction_date' => ['nullable', 'date'],
            'subtotal' => ['required', 'numeric', 'min:0'],
            'discount' => ['required', 'numeric', 'min:0'],
            'tax' => ['required', 'numeric', 'min:0'],
            'total' => ['required', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'uuid'],
            'items.*.product_variant_id' => ['nullable', 'uuid'],
            'items.*.location_id' => ['required', 'uuid'],
            'items.*.qty' => ['required', 'numeric', 'min:0.01'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.account_id' => ['required', 'uuid'],
            'payments.*.method' => ['required', 'string', 'max:50'],
            'payments.*.amount' => ['required', 'numeric', 'min:0.01'],
            'payments.*.payment_method_id' => ['nullable', 'uuid'],
        ]);

        try {
            $result = $this->service->handle($validated, $this->resolveActorId($request, $validated));

            return response()->json([
                'sale_id' => $result['sale_id'],
                'idempotent' => $result['idempotent'],
            ], $result['idempotent'] ? 200 : 201);
        } catch (LockPeriodException $exception) {
            return response()->json([
                'code' => $exception->errorCode(),
                'message' => $exception->getMessage(),
                'details' => [],
            ], $exception->status());
        } catch (InsufficientStockException $exception) {
            return response()->json([
                'code' => 'INSUFFICIENT_STOCK',
                'message' => $exception->getMessage(),
                'details' => $exception->context(),
            ], 409);
        } catch (ShiftValidationException $exception) {
            return response()->json([
                'code' => $exception->errorCode(),
                'message' => $exception->getMessage(),
                'details' => [],
            ], $exception->status());
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

            return response()->json([
                'code' => 'INVALID_TRANSACTION_DATE',
                'message' => $exception->getMessage(),
                'details' => [],
            ], 422);
        } catch (AutoConsumeValidationException $exception) {
            return response()->json([
                'code' => $exception->errorCode(),
                'message' => $exception->getMessage(),
                'details' => $exception->details(),
            ], $exception->status());
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'code' => 'INVALID_TOTAL',
                'message' => $exception->getMessage(),
                'details' => [],
            ], 422);
        }
    }

    private function resolveActorId(Request $request, array $validated): ?string
    {
        $actorId = $request->user()?->id;
        if ($actorId) {
            return $actorId;
        }

        if (! app()->environment('local')) {
            return null;
        }

        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return null;
        }

        return DB::table('shifts')
            ->where('tenant_id', $tenantId)
            ->where('id', (string) ($validated['shift_id'] ?? ''))
            ->where('status', Shift::STATUS_OPEN)
            ->value('user_id');
    }
}
