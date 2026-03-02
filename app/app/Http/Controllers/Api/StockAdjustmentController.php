<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Exceptions\AuthorizationLimitException;
use App\Services\StockAdjustmentService;
use App\Services\AnomalyService;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use App\Exceptions\LockPeriodException;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use RuntimeException;

class StockAdjustmentController extends Controller
{
    private const ADJUSTMENT_REASON_CODES = [
        'A01',
        'A02',
        'A03',
        'A04',
        'A99',
    ];

    public function store(Request $request, StockAdjustmentService $service)
    {
        $payload = $request->validate([
            'location_id' => ['required', 'uuid', 'exists:locations,id'],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'transaction_date' => ['nullable', 'date'],
            'reason_code' => ['required', Rule::in(self::ADJUSTMENT_REASON_CODES)],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'uuid', 'exists:products,id'],
            'items.*.physical_qty' => ['required', 'numeric', 'min:0'],
        ]);

        if (($payload['reason_code'] ?? null) === 'A99' && mb_strlen(trim((string) ($payload['notes'] ?? ''))) < 20) {
            return response()->json([
                'code' => 'REASON_NOTE_REQUIRED',
                'message' => 'Catatan minimal 20 karakter wajib untuk alasan A99.',
                'details' => [],
            ], 422);
        }

        try {
            $adjustmentId = $service->createDraft($payload, $request->user()?->id);

            return response()->json([
                'adjustment_id' => $adjustmentId,
                'status' => 'draft',
            ], 201);
        } catch (RuntimeException $exception) {
            return response()->json([
                'code' => 'ADJUSTMENT_CREATE_FAILED',
                'message' => $exception->getMessage(),
                'details' => [],
            ], 422);
        } catch (InvalidArgumentException $exception) {
            $tenantId = TenantContext::id();
            if ($tenantId) {
                app(AnomalyService::class)->raise(
                    tenantId: $tenantId,
                    severity: 'low',
                    type: 'invalid_transaction_date_attempt',
                    payload: [
                        'source' => 'adjustment_create',
                        'message' => $exception->getMessage(),
                    ]
                );
            }

            return response()->json([
                'code' => 'INVALID_TRANSACTION_DATE',
                'message' => $exception->getMessage(),
                'details' => [],
            ], 422);
        }
    }

    public function approve(Request $request, string $adjustment, StockAdjustmentService $service)
    {
        try {
            $service->approve($adjustment, $request->user()?->id);

            return response()->json([
                'status' => 'approved',
            ]);
        } catch (RuntimeException $exception) {
            return response()->json([
                'code' => 'ADJUSTMENT_APPROVE_FAILED',
                'message' => $exception->getMessage(),
                'details' => [],
            ], 422);
        }
    }

    public function post(Request $request, string $adjustment, StockAdjustmentService $service)
    {
        $payload = $request->validate([
            'transaction_date' => ['nullable', 'date'],
        ]);

        try {
            $service->post($adjustment, $request->user()?->id, [
                'ip_address' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
                'transaction_date' => $payload['transaction_date'] ?? null,
            ]);

            return response()->json([
                'status' => 'posted',
            ]);
        } catch (LockPeriodException $exception) {
            return response()->json([
                'code' => $exception->errorCode(),
                'message' => $exception->getMessage(),
                'details' => [],
            ], $exception->status());
        } catch (AuthorizationLimitException $exception) {
            return response()->json([
                'code' => $exception->errorCode(),
                'message' => $exception->getMessage(),
                'details' => $exception->details(),
            ], $exception->status());
        } catch (InvalidArgumentException $exception) {
            $tenantId = TenantContext::id();
            if ($tenantId) {
                app(AnomalyService::class)->raise(
                    tenantId: $tenantId,
                    severity: 'low',
                    type: 'invalid_transaction_date_attempt',
                    payload: [
                        'source' => 'adjustment_post',
                        'message' => $exception->getMessage(),
                    ]
                );
            }

            return response()->json([
                'code' => 'INVALID_TRANSACTION_DATE',
                'message' => $exception->getMessage(),
                'details' => [],
            ], 422);
        } catch (RuntimeException $exception) {
            return response()->json([
                'code' => 'ADJUSTMENT_POST_FAILED',
                'message' => $exception->getMessage(),
                'details' => [],
            ], 422);
        }
    }

    public function cancel(Request $request, string $adjustment, StockAdjustmentService $service)
    {
        try {
            $service->cancel($adjustment, $request->user()?->id);

            return response()->json([
                'status' => 'cancelled',
            ]);
        } catch (RuntimeException $exception) {
            return response()->json([
                'code' => 'ADJUSTMENT_CANCEL_FAILED',
                'message' => $exception->getMessage(),
                'details' => [],
            ], 422);
        }
    }
}
