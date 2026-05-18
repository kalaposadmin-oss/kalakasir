<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OpeningSession;
use App\Services\OpeningBalanceDraftService;
use App\Services\OpeningBalancePostingService;
use App\Services\OpeningBalanceValidationService;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use RuntimeException;

class OpeningBalanceController extends Controller
{
    public function createDraft(Request $request, OpeningBalanceDraftService $draftService)
    {
        $payload = $request->validate([
            'opening_date' => ['required', 'date'],
            'equity_account_id' => ['nullable', 'uuid'],
            'equity_amount' => ['nullable', 'numeric', 'min:0.01'],
            'meta' => ['nullable', 'array'],
        ]);

        try {
            $session = $draftService->createSession($payload, $request->user()?->id);

            return response()->json([
                'session' => $this->serializeSession($session),
            ], 201);
        } catch (RuntimeException $exception) {
            return response()->json([
                'code' => 'OPENING_BALANCE_DRAFT_FAILED',
                'message' => $exception->getMessage(),
                'details' => [],
            ], 422);
        }
    }

    public function currentDraft(Request $request)
    {
        $tenantId = TenantContext::id() ?: (string) $request->user()?->tenant_id;

        if ($tenantId === '') {
            return response()->json([
                'session' => null,
            ]);
        }

        $session = OpeningSession::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'draft')
            ->with(['stockLines', 'fundLines'])
            ->orderByDesc('updated_at')
            ->first();

        if (! $session) {
            return response()->json([
                'session' => null,
            ]);
        }

        return response()->json([
            'session' => $this->serializeSession($session),
        ]);
    }

    public function addStock(Request $request, string $session, OpeningBalanceDraftService $draftService)
    {
        $tenantId = TenantContext::id();
        $payload = $request->validate([
            'location_id' => ['required', 'uuid', $tenantId
                ? 'exists:locations,id,tenant_id,'.$tenantId
                : 'exists:locations,id'],
            'product_id' => ['required', 'uuid', $tenantId
                ? 'exists:products,id,tenant_id,'.$tenantId
                : 'exists:products,id'],
            'product_variant_id' => ['nullable', 'uuid'],
            'qty' => ['required', 'numeric', 'gt:0'],
            'unit_cost' => ['required', 'numeric', 'gt:0'],
            'reference_note' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $line = $draftService->addStockLine($session, $payload, $request->user()?->id);

            return response()->json([
                'line' => $line,
            ], 201);
        } catch (RuntimeException $exception) {
            return response()->json([
                'code' => 'OPENING_BALANCE_STOCK_LINE_FAILED',
                'message' => $exception->getMessage(),
                'details' => [],
            ], 422);
        }
    }

    public function updateStock(Request $request, string $session, string $line, OpeningBalanceDraftService $draftService)
    {
        $tenantId = TenantContext::id();
        $payload = $request->validate([
            'location_id' => ['required', 'uuid', $tenantId
                ? 'exists:locations,id,tenant_id,'.$tenantId
                : 'exists:locations,id'],
            'product_id' => ['required', 'uuid', $tenantId
                ? 'exists:products,id,tenant_id,'.$tenantId
                : 'exists:products,id'],
            'product_variant_id' => ['nullable', 'uuid'],
            'qty' => ['required', 'numeric', 'gt:0'],
            'unit_cost' => ['required', 'numeric', 'gt:0'],
            'reference_note' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $updatedLine = $draftService->updateStockLine($session, $line, $payload + [
                'tenant_id' => $tenantId,
            ]);

            return response()->json([
                'line' => $updatedLine,
            ]);
        } catch (RuntimeException $exception) {
            return response()->json([
                'code' => 'OPENING_BALANCE_STOCK_LINE_UPDATE_FAILED',
                'message' => $exception->getMessage(),
                'details' => [],
            ], 422);
        }
    }

    public function addFund(Request $request, string $session, OpeningBalanceDraftService $draftService)
    {
        $tenantId = TenantContext::id();
        $payload = $request->validate([
            'account_id' => ['required', 'uuid', $tenantId
                ? 'exists:chart_of_accounts,id,tenant_id,'.$tenantId
                : 'exists:chart_of_accounts,id'],
            'source_type' => ['required', 'in:cash,bank'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reference_note' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $line = $draftService->addFundLine($session, $payload, $request->user()?->id);

            return response()->json([
                'line' => $line,
            ], 201);
        } catch (RuntimeException $exception) {
            return response()->json([
                'code' => 'OPENING_BALANCE_FUND_LINE_FAILED',
                'message' => $exception->getMessage(),
                'details' => [],
            ], 422);
        }
    }

    public function updateFund(Request $request, string $session, string $line, OpeningBalanceDraftService $draftService)
    {
        $tenantId = TenantContext::id();
        $payload = $request->validate([
            'account_id' => ['required', 'uuid', $tenantId
                ? 'exists:chart_of_accounts,id,tenant_id,'.$tenantId
                : 'exists:chart_of_accounts,id'],
            'source_type' => ['required', 'in:cash,bank'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reference_note' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $updatedLine = $draftService->updateFundLine($session, $line, $payload + [
                'tenant_id' => $tenantId,
            ]);

            return response()->json([
                'line' => $updatedLine,
            ]);
        } catch (RuntimeException $exception) {
            return response()->json([
                'code' => 'OPENING_BALANCE_FUND_LINE_UPDATE_FAILED',
                'message' => $exception->getMessage(),
                'details' => [],
            ], 422);
        }
    }

    public function deleteStock(Request $request, string $session, string $line, OpeningBalanceDraftService $draftService)
    {
        try {
            $draftService->deleteStockLine($session, $line, TenantContext::id());

            return response()->json([
                'status' => 'deleted',
                'line_id' => $line,
            ]);
        } catch (RuntimeException $exception) {
            return response()->json([
                'code' => 'OPENING_BALANCE_STOCK_LINE_DELETE_FAILED',
                'message' => $exception->getMessage(),
                'details' => [],
            ], 422);
        }
    }

    public function deleteFund(Request $request, string $session, string $line, OpeningBalanceDraftService $draftService)
    {
        try {
            $draftService->deleteFundLine($session, $line, TenantContext::id());

            return response()->json([
                'status' => 'deleted',
                'line_id' => $line,
            ]);
        } catch (RuntimeException $exception) {
            return response()->json([
                'code' => 'OPENING_BALANCE_FUND_LINE_DELETE_FAILED',
                'message' => $exception->getMessage(),
                'details' => [],
            ], 422);
        }
    }

    public function validateDraft(Request $request, OpeningBalanceValidationService $validationService)
    {
        $payload = $request->validate([
            'session_id' => ['required', 'uuid'],
        ]);

        $tenantId = TenantContext::id();
        $session = OpeningSession::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $payload['session_id'])
            ->with(['stockLines', 'fundLines'])
            ->firstOrFail();

        try {
            $totals = $validationService->validateForPost($session);

            return response()->json([
                'status' => 'valid',
                'totals' => $totals,
            ]);
        } catch (RuntimeException $exception) {
            return response()->json([
                'status' => 'invalid',
                'code' => 'OPENING_BALANCE_INVALID',
                'message' => $exception->getMessage(),
                'details' => [],
            ], 422);
        }
    }

    public function post(Request $request, OpeningBalancePostingService $postingService)
    {
        $payload = $request->validate([
            'session_id' => ['required', 'uuid'],
        ]);

        try {
            $session = $postingService->post(
                sessionId: $payload['session_id'],
                actorId: $request->user()?->id,
                context: [
                    'ip_address' => $request->ip(),
                    'user_agent' => (string) $request->userAgent(),
                ]
            );

            return response()->json([
                'status' => 'posted',
                'session' => $this->serializeSession($session),
            ]);
        } catch (RuntimeException $exception) {
            return response()->json([
                'code' => 'OPENING_BALANCE_POST_FAILED',
                'message' => $exception->getMessage(),
                'details' => [],
            ], 422);
        }
    }

    private function serializeSession(OpeningSession $session): array
    {
        $payload = $session->toArray();

        if ($session->opening_date !== null) {
            $payload['opening_date'] = $session->opening_date->toDateString();
        }

        return $payload;
    }
}
