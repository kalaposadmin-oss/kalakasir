<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Exceptions\AuthorizationLimitException;
use App\Exceptions\ManagerPinException;
use App\Services\VoidService;
use App\Support\GovernanceRequestContext;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use App\Exceptions\LockPeriodException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class VoidSaleController extends Controller
{
    private const VOID_REASON_CODES = [
        'V01',
        'V02',
        'V03',
        'V04',
        'V99',
    ];

    public function __construct(private readonly VoidService $service)
    {
    }

    public function __invoke(Request $request, string $sale)
    {
        if (! TenantContext::id()) {
            return response()->json([
                'code' => 'TENANT_NOT_RESOLVED',
                'message' => 'Tenant tidak ditemukan.',
                'details' => [],
            ], 400);
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
            'reason_code' => ['nullable', Rule::in(self::VOID_REASON_CODES)],
            'reason_note' => ['nullable', 'string', 'max:255'],
            'manager_pin' => ['nullable', 'string', 'max:64'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
            ...GovernanceRequestContext::validationRules(),
        ]);

        try {
            $governance = GovernanceRequestContext::fromRequest($request, $validated);
            GovernanceRequestContext::enforceRoleConsistency($governance);
            GovernanceRequestContext::enforceApprovalPolicy($governance, 'void_sale');

            $reasonCode = $validated['reason_code'] ?? null;
            $reasonNote = trim((string) ($validated['reason_note'] ?? ''));

            if ($reasonCode === 'V99' && mb_strlen($reasonNote) < 20) {
                return response()->json([
                    'code' => 'REASON_NOTE_REQUIRED',
                    'message' => 'Catatan minimal 20 karakter wajib untuk alasan Lainnya.',
                    'details' => [],
                ], 422);
            }

            $reason = $validated['reason'] ?? null;
            if ($reasonCode) {
                $reason = $reasonCode;
                if ($reasonNote !== '') {
                    $reason .= ' - ' . $reasonNote;
                }
            }

            $idempotencyKey = $validated['idempotency_key'] ?? $request->header('Idempotency-Key') ?? $request->header('X-Idempotency-Key');
            $idempotencyKey = is_string($idempotencyKey) ? trim($idempotencyKey) : null;
            $idempotencyKey = $idempotencyKey !== '' ? $idempotencyKey : null;

            $this->service->handle(
                saleId: $sale,
                userId: $request->user()?->id,
                reason: $reason,
                managerPin: $validated['manager_pin'] ?? null,
                clientIp: $request->ip(),
                userAgent: substr((string) $request->userAgent(), 0, 255),
                requestId: $request->header('X-Request-Id'),
                idempotencyKey: $idempotencyKey,
            );

            return response()->json([
                'status' => 'voided',
                'governance' => GovernanceRequestContext::responseMeta($governance),
            ]);
        } catch (ValidationException $exception) {
            return response()->json([
                'code' => 'VALIDATION_ERROR',
                'message' => 'Data tidak valid.',
                'details' => $exception->errors(),
            ], 422);
        } catch (ManagerPinException $exception) {
            return response()->json([
                'code' => $exception->errorCode(),
                'message' => $exception->getMessage(),
                'details' => $exception->details(),
            ], $exception->status());
        } catch (AuthorizationLimitException $exception) {
            return response()->json([
                'code' => $exception->errorCode(),
                'message' => $exception->getMessage(),
                'details' => $exception->details(),
            ], $exception->status());
        } catch (LockPeriodException $exception) {
            return response()->json([
                'code' => $exception->errorCode(),
                'message' => $exception->getMessage(),
                'details' => [],
            ], $exception->status());
        } catch (RuntimeException $exception) {
            return response()->json([
                'code' => 'VOID_FAILED',
                'message' => $exception->getMessage(),
                'details' => [],
            ], 422);
        }
    }
}
