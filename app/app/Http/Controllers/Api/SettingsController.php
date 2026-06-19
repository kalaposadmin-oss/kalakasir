<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\RunValuationRebuildJob;
use App\Models\Promo;
use App\Models\Tenant;
use App\Models\Tax;
use App\Services\AnomalyService;
use App\Services\InventoryValuationService;
use App\Services\ValuationAuditService;
use App\Services\ValuationRebuildStateService;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SettingsController extends Controller
{
    public function index()
    {
        $tenantId = TenantContext::id();

        $taxes = Tax::query()
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get(['id', 'name', 'description', 'rate', 'is_active']);

        $promos = Promo::query()
            ->orderByDesc('status')
            ->orderByDesc('ends_at')
            ->get([
                'id',
                'name',
                'type',
                'value',
                'min_purchase',
                'category',
                'code',
                'status',
                'ends_at',
            ]);

        $lastMethodChange = null;
        if ($tenantId) {
            $lastMethodChange = DB::table('valuation_audit_logs as v')
                ->leftJoin('users as u', function ($join) {
                    $join->on('v.actor_id', '=', 'u.id')
                        ->on('v.tenant_id', '=', 'u.tenant_id');
                })
                ->where('v.tenant_id', $tenantId)
                ->where('v.action', 'valuation_method_updated')
                ->orderByDesc('v.created_at')
                ->first([
                    'v.created_at as changed_at',
                    'v.actor_id as changed_by',
                    'u.name as changed_by_name',
                    'v.meta_json',
                ]);
        }

        return response()->json([
            'taxes' => $taxes,
            'promos' => $promos,
            'inventory_valuation_method' => TenantContext::get()?->inventory_valuation_method ?? 'average',
            'valuation_method_last_change' => $lastMethodChange,
            'valuation_rebuild' => app(ValuationRebuildStateService::class)->meta((string) TenantContext::id()),
        ]);
    }

    public function storeTax(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json([
                'message' => 'Tenant tidak ditemukan.',
            ], 400);
        }

        $tax = Tax::query()->create([
            'tenant_id' => $tenantId,
            'name' => trim((string) $validated['name']),
            'description' => trim((string) ($validated['description'] ?? '')) ?: null,
            'rate' => $validated['rate'],
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return response()->json([
            'status' => 'ok',
            'tax' => [
                'id' => $tax->id,
                'name' => $tax->name,
                'description' => $tax->description,
                'rate' => (float) $tax->rate,
                'is_active' => (bool) $tax->is_active,
            ],
        ], 201);
    }
    public function updateTax(Request $request, string $tax)
    {
        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $taxRow = Tax::query()->where('id', $tax)->firstOrFail();
        $taxRow->update([
            'is_active' => $validated['is_active'],
        ]);

        return response()->json([
            'status' => 'ok',
        ]);
    }

    public function updateRoundingSettings(Request $request)
    {
        $validated = $request->validate([
            'mode' => ['required', 'in:none,nearest,up,down,multiple'],
            'increment' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);

        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json([
                'message' => 'Tenant tidak ditemukan.',
            ], 400);
        }

        $mode = $validated['mode'];
        $increment = (int) ($validated['increment'] ?? 0);

        if ($mode === 'multiple' && $increment <= 0) {
            return response()->json([
                'code' => 'ROUNDING_INCREMENT_REQUIRED',
                'message' => 'Nilai pembulatan wajib diisi untuk mode kelipatan.',
                'details' => [],
            ], 422);
        }

        if ($mode !== 'multiple') {
            $increment = 0;
        }

        Tenant::query()
            ->where('id', $tenantId)
            ->update([
                'rounding_mode' => $mode,
                'rounding_increment' => $increment,
            ]);

        return response()->json([
            'status' => 'ok',
            'rounding_settings' => [
                'mode' => $mode,
                'increment' => $increment,
            ],
        ]);
    }
    public function updateInventoryValuationMethod(Request $request)
    {
        $validated = $request->validate([
            'method' => ['required', 'in:fifo,average'],
            'confirm_rebuild' => ['required', 'boolean'],
        ]);

        if (! $validated['confirm_rebuild']) {
            return response()->json([
                'code' => 'CONFIRM_REBUILD_REQUIRED',
                'message' => 'Perubahan metode valuation wajib konfirmasi rebuild.',
                'details' => [],
            ], 422);
        }

        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json([
                'message' => 'Tenant tidak ditemukan.',
            ], 400);
        }

        $state = app(ValuationRebuildStateService::class);
        if ($state->isLocked($tenantId)) {
            return response()->json([
                'code' => 'VALUATION_REBUILD_IN_PROGRESS',
                'message' => 'Rebuild valuation sedang berjalan.',
                'details' => [],
            ], 423);
        }

        $currentMethod = Tenant::query()
            ->where('id', $tenantId)
            ->value('inventory_valuation_method') ?? 'average';

        if ($currentMethod === $validated['method']) {
            return response()->json([
                'status' => 'ok',
                'inventory_valuation_method' => $currentMethod,
                'rebuild_triggered' => false,
            ]);
        }

        app(ValuationAuditService::class)->log($tenantId, 'valuation_method_update_requested', [
            'actor_id' => $request->user()?->id,
            'from' => $currentMethod,
            'to' => $validated['method'],
        ], $request);

        $state->lock($tenantId, 3600);

        try {
            DB::transaction(function () use ($tenantId, $validated): void {
                Tenant::query()
                    ->where('id', $tenantId)
                    ->update([
                        'inventory_valuation_method' => $validated['method'],
                    ]);

                app(InventoryValuationService::class)->replayFromLedger($tenantId);
            }, 3);

            app(AnomalyService::class)->raise(
                tenantId: $tenantId,
                severity: 'low',
                type: 'valuation_method_changed',
                payload: [
                    'from' => $currentMethod,
                    'to' => $validated['method'],
                    'source' => 'settings.update_inventory_valuation_method',
                    'actor_id' => $request->user()?->id,
                ]
            );

            app(ValuationAuditService::class)->log($tenantId, 'valuation_method_updated', [
                'actor_id' => $request->user()?->id,
                'from' => $currentMethod,
                'to' => $validated['method'],
            ], $request);

            $state->unlock($tenantId, true, 'valuation rebuild success');
        } catch (\Throwable $exception) {
            $state->unlock($tenantId, false, $exception->getMessage());

            app(ValuationAuditService::class)->log($tenantId, 'valuation_method_update_failed', [
                'actor_id' => $request->user()?->id,
                'from' => $currentMethod,
                'to' => $validated['method'],
                'message' => $exception->getMessage(),
            ], $request);

            throw $exception;
        }

        return response()->json([
            'status' => 'ok',
            'inventory_valuation_method' => $validated['method'],
            'rebuild_triggered' => true,
        ]);
    }

    public function triggerInventoryValuationRebuild(Request $request)
    {
        $validated = $request->validate([
            'trigger' => ['nullable', 'string', 'max:120'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
        ]);

        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json([
                'message' => 'Tenant tidak ditemukan.',
            ], 400);
        }

        $state = app(ValuationRebuildStateService::class);
        if ($state->isLocked($tenantId)) {
            return response()->json([
                'code' => 'VALUATION_REBUILD_IN_PROGRESS',
                'message' => 'Rebuild valuation sedang berjalan.',
                'details' => $this->withRuntime($state->meta($tenantId)),
            ], 423);
        }

        $idempotencyKey = $validated['idempotency_key'] ?? $request->header('Idempotency-Key');
        if (is_string($idempotencyKey) && trim($idempotencyKey) !== '') {
            $cacheKey = 'valuation:rebuild:idempotency:'.$tenantId.':'.sha1($idempotencyKey);
            $existingRequestId = Cache::get($cacheKey);
            if ($existingRequestId && ($state->meta($tenantId)['request_id'] ?? null) === $existingRequestId) {
                return response()->json([
                    'status' => 'duplicate',
                    'request_id' => $existingRequestId,
                ], 202);
            }
        }

        $requestId = (string) Str::uuid();
        $state->lock($tenantId, 3600, [
            'request_id' => $requestId,
            'actor_id' => $request->user()?->id,
            'trigger' => $validated['trigger'] ?? 'manual',
        ]);

        RunValuationRebuildJob::dispatch(
            tenantId: $tenantId,
            requestId: $requestId,
            actorId: $request->user()?->id,
            trigger: $validated['trigger'] ?? 'manual',
        );

        if (isset($cacheKey)) {
            Cache::put($cacheKey, $requestId, 60 * 10);
        }

        app(ValuationAuditService::class)->log($tenantId, 'valuation_rebuild_queued', [
            'request_id' => $requestId,
            'actor_id' => $request->user()?->id,
            'trigger' => $validated['trigger'] ?? 'manual',
        ], $request);

        return response()->json([
            'status' => 'queued',
            'request_id' => $requestId,
        ], 202);
    }

    public function retryInventoryValuationRebuild(Request $request)
    {
        $validated = $request->validate([
            'request_id' => ['required', 'uuid'],
            'trigger' => ['nullable', 'string', 'max:120'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
        ]);

        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json([
                'message' => 'Tenant tidak ditemukan.',
            ], 400);
        }

        $state = app(ValuationRebuildStateService::class);
        $meta = $state->meta($tenantId);
        if (($meta['request_id'] ?? null) !== $validated['request_id']) {
            return response()->json([
                'code' => 'REQUEST_NOT_FOUND',
                'message' => 'Request rebuild tidak ditemukan.',
                'details' => [],
            ], 404);
        }

        if (! in_array($meta['status'] ?? null, ['failed', 'cancelled'], true)) {
            return response()->json([
                'code' => 'RETRY_NOT_ALLOWED',
                'message' => 'Hanya request failed/cancelled yang bisa di-retry.',
                'details' => [
                    'status' => $meta['status'] ?? null,
                ],
            ], 422);
        }

        if ($state->isLocked($tenantId)) {
            return response()->json([
                'code' => 'VALUATION_REBUILD_IN_PROGRESS',
                'message' => 'Rebuild valuation sedang berjalan.',
                'details' => $this->withRuntime($meta),
            ], 423);
        }

        $nextTrigger = $validated['trigger'] ?? ('retry:'.$validated['request_id']);
        $requestId = (string) Str::uuid();

        $state->lock($tenantId, 3600, [
            'request_id' => $requestId,
            'actor_id' => $request->user()?->id,
            'trigger' => $nextTrigger,
        ]);

        RunValuationRebuildJob::dispatch(
            tenantId: $tenantId,
            requestId: $requestId,
            actorId: $request->user()?->id,
            trigger: $nextTrigger,
        );

        app(ValuationAuditService::class)->log($tenantId, 'valuation_rebuild_retried', [
            'request_id' => $requestId,
            'retried_from' => $validated['request_id'],
            'actor_id' => $request->user()?->id,
            'trigger' => $nextTrigger,
        ], $request);

        return response()->json([
            'status' => 'queued',
            'request_id' => $requestId,
            'retried_from' => $validated['request_id'],
        ], 202);
    }

    public function cancelInventoryValuationRebuild(Request $request)
    {
        $validated = $request->validate([
            'request_id' => ['required', 'uuid'],
            'reason' => ['nullable', 'string', 'max:300'],
        ]);

        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json([
                'message' => 'Tenant tidak ditemukan.',
            ], 400);
        }

        $state = app(ValuationRebuildStateService::class);
        $meta = $state->meta($tenantId);

        if (($meta['request_id'] ?? null) !== $validated['request_id']) {
            return response()->json([
                'code' => 'REQUEST_NOT_FOUND',
                'message' => 'Request rebuild tidak ditemukan.',
                'details' => [],
            ], 404);
        }

        if (in_array($meta['status'] ?? null, ['ok', 'failed', 'cancelled'], true)) {
            return response()->json([
                'status' => $meta['status'],
                'request_id' => $validated['request_id'],
                'message' => 'Rebuild sudah selesai atau dibatalkan sebelumnya.',
            ]);
        }

        $state->markCancelled($tenantId, $validated['reason'] ?? 'cancelled by operator', [
            'request_id' => $validated['request_id'],
            'actor_id' => $request->user()?->id,
            'trigger' => $meta['trigger'] ?? 'manual',
        ]);

        app(ValuationAuditService::class)->log($tenantId, 'valuation_rebuild_cancelled', [
            'request_id' => $validated['request_id'],
            'actor_id' => $request->user()?->id,
            'reason' => $validated['reason'] ?? null,
        ], $request);

        return response()->json([
            'status' => 'cancelled',
            'request_id' => $validated['request_id'],
        ]);
    }

    public function valuationRebuildStatus()
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json([
                'message' => 'Tenant tidak ditemukan.',
            ], 400);
        }

        $state = app(ValuationRebuildStateService::class);

        return response()->json([
            'locked' => $state->isLocked($tenantId),
            'meta' => $this->withRuntime($state->meta($tenantId)),
        ]);
    }

    public function valuationMethodImpactPreview(Request $request)
    {
        $validated = $request->validate([
            'target_method' => ['required', 'in:fifo,average'],
        ]);

        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json([
                'message' => 'Tenant tidak ditemukan.',
            ], 400);
        }

        $currentMethod = Tenant::query()
            ->where('id', $tenantId)
            ->value('inventory_valuation_method') ?? 'average';

        $targetMethod = (string) $validated['target_method'];

        $salesCount = (int) DB::table('sales')
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->count();

        $saleItemsCount = (int) DB::table('sale_items')
            ->where('tenant_id', $tenantId)
            ->count();

        $ledgerRowsCount = (int) DB::table('stock_ledger')
            ->where('tenant_id', $tenantId)
            ->count();

        $trackedProductsCount = (int) DB::table('products')
            ->where('tenant_id', $tenantId)
            ->where('track_stock', true)
            ->count();

        $timeline = DB::table('stock_ledger')
            ->where('tenant_id', $tenantId)
            ->selectRaw('min(created_at) as first_tx_at')
            ->selectRaw('max(created_at) as last_tx_at')
            ->first();

        return response()->json([
            'current_method' => $currentMethod,
            'target_method' => $targetMethod,
            'will_rebuild' => $currentMethod !== $targetMethod,
            'impact' => [
                'sales_completed' => $salesCount,
                'sale_items' => $saleItemsCount,
                'stock_ledger_rows' => $ledgerRowsCount,
                'tracked_products' => $trackedProductsCount,
                'timeline' => [
                    'first_tx_at' => $timeline?->first_tx_at,
                    'last_tx_at' => $timeline?->last_tx_at,
                ],
            ],
        ]);
    }

    private function withRuntime(array $meta): array
    {
        $startedAt = isset($meta['started_at']) && is_string($meta['started_at'])
            ? CarbonImmutable::parse($meta['started_at'])
            : null;

        if (! $startedAt) {
            $meta['runtime_ms'] = null;
            return $meta;
        }

        $endedAt = isset($meta['finished_at']) && is_string($meta['finished_at'])
            ? CarbonImmutable::parse($meta['finished_at'])
            : CarbonImmutable::now('UTC');

        $meta['runtime_ms'] = max(0, $endedAt->diffInMilliseconds($startedAt));

        return $meta;
    }
}









