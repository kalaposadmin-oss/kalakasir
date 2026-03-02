<?php

namespace App\Http\Controllers\Api;

use App\Jobs\ExportValuationComparisonJob;
use App\Http\Controllers\Controller;
use App\Services\ValuationAuditService;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ValuationReportController extends Controller
{
    public function index()
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json([
                'code' => 'TENANT_NOT_RESOLVED',
                'message' => 'Tenant tidak ditemukan.',
                'details' => [],
            ], 400);
        }

        $dir = storage_path('app/reports');

        if (! is_dir($dir)) {
            return response()->json([
                'reports' => [],
            ]);
        }

        $tenantPrefix = 'valuation-comparison-'.$tenantId.'-';

        $files = collect(scandir($dir) ?: [])
            ->filter(fn (string $name) => Str::startsWith($name, $tenantPrefix))
            ->filter(fn (string $name) => Str::endsWith($name, ['.csv', '.md']))
            ->map(function (string $name) use ($dir) {
                $path = $dir.DIRECTORY_SEPARATOR.$name;

                return [
                    'name' => $name,
                    'size' => filesize($path) ?: 0,
                    'modified_at' => gmdate('c', filemtime($path) ?: time()),
                    'extension' => pathinfo($name, PATHINFO_EXTENSION),
                ];
            })
            ->sortByDesc('modified_at')
            ->values()
            ->all();

        return response()->json([
            'reports' => $files,
        ]);
    }

    public function download(Request $request, string $filename)
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            abort(404);
        }

        if (str_contains($filename, '..') || str_contains($filename, '/') || str_contains($filename, '\\')) {
            abort(404);
        }

        if (! Str::startsWith($filename, 'valuation-comparison-'.$tenantId.'-')) {
            abort(404);
        }

        if (! Str::endsWith($filename, ['.csv', '.md'])) {
            abort(404);
        }

        $path = storage_path('app/reports'.DIRECTORY_SEPARATOR.$filename);
        if (! file_exists($path)) {
            abort(404);
        }

        return response()->download($path, $filename);
    }

    public function export(Request $request)
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json([
                'code' => 'TENANT_NOT_RESOLVED',
                'message' => 'Tenant tidak ditemukan.',
                'details' => [],
            ], 400);
        }

        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'sku_prefix' => ['nullable', 'string', 'max:60'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
        ]);

        $idempotencyKey = $validated['idempotency_key'] ?? $request->header('Idempotency-Key');
        if (is_string($idempotencyKey) && trim($idempotencyKey) !== '') {
            $fingerprint = sha1(json_encode([
                'from' => $validated['from'] ?? null,
                'to' => $validated['to'] ?? null,
                'sku_prefix' => $validated['sku_prefix'] ?? null,
                'limit' => (int) ($validated['limit'] ?? 200),
            ], JSON_UNESCAPED_SLASHES));
            $idempotencyCacheKey = 'valuation:export:idempotency:'.$tenantId.':'.sha1($idempotencyKey).'::'.$fingerprint;
            $existingRequestId = Cache::get($idempotencyCacheKey);
            if ($existingRequestId) {
                $existingStatus = Cache::get('valuation:export:request:'.$existingRequestId);
                if (($existingStatus['status'] ?? null) && in_array($existingStatus['status'], ['queued', 'running'], true)) {
                    return response()->json([
                        'status' => 'duplicate',
                        'request_id' => $existingRequestId,
                    ], 202);
                }
            }
        }

        $requestId = (string) Str::uuid();
        $key = 'valuation:export:request:'.$requestId;

        Cache::put($key, [
            'status' => 'queued',
            'request_id' => $requestId,
            'tenant_id' => $tenantId,
            'actor_id' => $request->user()?->id,
            'queued_at' => now('UTC')->toIso8601String(),
            'params' => [
                'from' => $validated['from'] ?? null,
                'to' => $validated['to'] ?? null,
                'sku_prefix' => $validated['sku_prefix'] ?? null,
                'limit' => (int) ($validated['limit'] ?? 200),
            ],
        ], 60 * 60 * 24);

        ExportValuationComparisonJob::dispatch(
            requestId: $requestId,
            tenantId: $tenantId,
            fromDate: $validated['from'] ?? null,
            toDate: $validated['to'] ?? null,
            skuPrefix: $validated['sku_prefix'] ?? null,
            limit: (int) ($validated['limit'] ?? 200),
            actorId: $request->user()?->id,
        );

        if (isset($idempotencyCacheKey)) {
            Cache::put($idempotencyCacheKey, $requestId, 60 * 10);
        }

        app(ValuationAuditService::class)->log($tenantId, 'valuation_export_queued', [
            'request_id' => $requestId,
            'actor_id' => $request->user()?->id,
            'from' => $validated['from'] ?? null,
            'to' => $validated['to'] ?? null,
            'sku_prefix' => $validated['sku_prefix'] ?? null,
            'limit' => (int) ($validated['limit'] ?? 200),
        ], $request);

        return response()->json([
            'status' => 'queued',
            'request_id' => $requestId,
        ], 202);
    }

    public function exportStatus(Request $request, string $requestId)
    {
        if (! Str::isUuid($requestId)) {
            return response()->json([
                'code' => 'INVALID_REQUEST_ID',
                'message' => 'Request id tidak valid.',
                'details' => [],
            ], 422);
        }

        $key = 'valuation:export:request:'.$requestId;
        $status = Cache::get($key);

        if (! $status) {
            return response()->json([
                'code' => 'REQUEST_NOT_FOUND',
                'message' => 'Request export tidak ditemukan atau sudah expired.',
                'details' => [],
            ], 404);
        }

        if (($status['tenant_id'] ?? null) !== TenantContext::id()) {
            return response()->json([
                'code' => 'FORBIDDEN',
                'message' => 'Request export bukan milik tenant ini.',
                'details' => [],
            ], 403);
        }

        return response()->json($this->withRuntime($status));
    }

    public function retryExport(Request $request)
    {
        $validated = $request->validate([
            'request_id' => ['required', 'uuid'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
        ]);

        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json([
                'code' => 'TENANT_NOT_RESOLVED',
                'message' => 'Tenant tidak ditemukan.',
                'details' => [],
            ], 400);
        }

        $previous = Cache::get('valuation:export:request:'.$validated['request_id']);
        if (! $previous || ($previous['tenant_id'] ?? null) !== $tenantId) {
            return response()->json([
                'code' => 'REQUEST_NOT_FOUND',
                'message' => 'Request export tidak ditemukan.',
                'details' => [],
            ], 404);
        }

        if (! in_array($previous['status'] ?? null, ['failed', 'cancelled'], true)) {
            return response()->json([
                'code' => 'RETRY_NOT_ALLOWED',
                'message' => 'Hanya request failed/cancelled yang bisa di-retry.',
                'details' => [
                    'status' => $previous['status'] ?? null,
                ],
            ], 422);
        }

        $params = $previous['params'] ?? [];
        $requestId = (string) Str::uuid();
        $key = 'valuation:export:request:'.$requestId;

        Cache::put($key, [
            'status' => 'queued',
            'request_id' => $requestId,
            'tenant_id' => $tenantId,
            'actor_id' => $request->user()?->id,
            'queued_at' => now('UTC')->toIso8601String(),
            'params' => [
                'from' => $params['from'] ?? null,
                'to' => $params['to'] ?? null,
                'sku_prefix' => $params['sku_prefix'] ?? null,
                'limit' => (int) ($params['limit'] ?? 200),
            ],
            'retried_from' => $validated['request_id'],
        ], 60 * 60 * 24);

        ExportValuationComparisonJob::dispatch(
            requestId: $requestId,
            tenantId: $tenantId,
            fromDate: $params['from'] ?? null,
            toDate: $params['to'] ?? null,
            skuPrefix: $params['sku_prefix'] ?? null,
            limit: (int) ($params['limit'] ?? 200),
            actorId: $request->user()?->id,
        );

        app(ValuationAuditService::class)->log($tenantId, 'valuation_export_retried', [
            'request_id' => $requestId,
            'retried_from' => $validated['request_id'],
            'actor_id' => $request->user()?->id,
            'params' => [
                'from' => $params['from'] ?? null,
                'to' => $params['to'] ?? null,
                'sku_prefix' => $params['sku_prefix'] ?? null,
                'limit' => (int) ($params['limit'] ?? 200),
            ],
        ], $request);

        return response()->json([
            'status' => 'queued',
            'request_id' => $requestId,
            'retried_from' => $validated['request_id'],
        ], 202);
    }

    public function cancelExport(Request $request)
    {
        $validated = $request->validate([
            'request_id' => ['required', 'uuid'],
            'reason' => ['nullable', 'string', 'max:300'],
        ]);

        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json([
                'code' => 'TENANT_NOT_RESOLVED',
                'message' => 'Tenant tidak ditemukan.',
                'details' => [],
            ], 400);
        }

        $key = 'valuation:export:request:'.$validated['request_id'];
        $status = Cache::get($key);

        if (! $status) {
            return response()->json([
                'code' => 'REQUEST_NOT_FOUND',
                'message' => 'Request export tidak ditemukan atau sudah expired.',
                'details' => [],
            ], 404);
        }

        if (($status['tenant_id'] ?? null) !== $tenantId) {
            return response()->json([
                'code' => 'FORBIDDEN',
                'message' => 'Request export bukan milik tenant ini.',
                'details' => [],
            ], 403);
        }

        if (in_array($status['status'] ?? null, ['success', 'failed', 'cancelled'], true)) {
            return response()->json([
                'status' => $status['status'],
                'request_id' => $validated['request_id'],
                'message' => 'Export sudah selesai atau sudah dibatalkan.',
            ]);
        }

        Cache::put($key, [
            ...$status,
            'status' => 'cancelled',
            'finished_at' => now('UTC')->toIso8601String(),
            'message' => $validated['reason'] ?? 'cancelled by operator',
            'cancelled_by' => $request->user()?->id,
        ], 60 * 60 * 24 * 7);

        app(ValuationAuditService::class)->log($tenantId, 'valuation_export_cancelled', [
            'request_id' => $validated['request_id'],
            'actor_id' => $request->user()?->id,
            'reason' => $validated['reason'] ?? null,
        ], $request);

        return response()->json([
            'status' => 'cancelled',
            'request_id' => $validated['request_id'],
        ]);
    }

    private function withRuntime(array $status): array
    {
        $startedAtValue = $status['started_at'] ?? $status['queued_at'] ?? null;
        $startedAt = is_string($startedAtValue) ? CarbonImmutable::parse($startedAtValue) : null;

        if (! $startedAt) {
            $status['runtime_ms'] = null;
            return $status;
        }

        $finishedAt = is_string($status['finished_at'] ?? null)
            ? CarbonImmutable::parse($status['finished_at'])
            : CarbonImmutable::now('UTC');

        $status['runtime_ms'] = max(0, $finishedAt->diffInMilliseconds($startedAt));

        return $status;
    }
}
