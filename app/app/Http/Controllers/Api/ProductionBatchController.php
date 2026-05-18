<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\LockPeriodException;
use App\Http\Controllers\Controller;
use App\Models\Anomaly;
use App\Models\ProductionBatch;
use App\Models\ProductionBatchItem;
use App\Services\AnomalyService;
use App\Services\ProductionEligibilityService;
use App\Services\ProductionBatchService;
use App\Support\ApiResponse;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductionBatchController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = TenantContext::id();

        $filters = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:300'],
            'product_id' => ['nullable', 'uuid'],
            'sort_by' => ['nullable', Rule::in(['newest', 'oldest', 'largest_variance', 'latest_anomaly'])],
            'status' => ['nullable', Rule::in([
                ProductionBatch::STATUS_DRAFT,
                ProductionBatch::STATUS_POSTED,
                ProductionBatch::STATUS_CANCELLED,
            ])],
            'cancel_reason' => ['nullable', 'string', 'max:2000'],
            'has_open_anomaly' => ['nullable', 'in:0,1'],
            'branch_id' => ['nullable', 'uuid'],
            'location_id' => ['nullable', 'uuid'],
            'transaction_date_from' => ['nullable', 'date_format:Y-m-d'],
            'transaction_date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:transaction_date_from'],
        ]);

        $limit = (int) ($filters['limit'] ?? 100);
        $sortBy = (string) ($filters['sort_by'] ?? 'newest');

        $anomalySummarySubquery = Anomaly::query()
            ->where('tenant_id', $tenantId)
            ->where('type', 'production_batch_yield_variance')
            ->select('reference_id')
            ->selectRaw('count(*) as total_count')
            ->selectRaw('sum(case when resolved_at is null then 1 else 0 end) as open_count')
            ->selectRaw('sum(case when resolved_at is not null then 1 else 0 end) as resolved_count')
            ->selectRaw('max(case when resolved_at is null then detected_at else null end) as latest_open_detected_at')
            ->selectRaw('max(detected_at) as latest_detected_at')
            ->groupBy('reference_id');

        $batches = ProductionBatch::query()
            ->leftJoinSub($anomalySummarySubquery, 'production_batch_anomalies', function ($join) {
                $join->on('production_batch_anomalies.reference_id', '=', 'production_batches.id');
            })
            ->where('tenant_id', $tenantId)
            ->select('production_batches.*')
            ->selectRaw('coalesce(production_batch_anomalies.open_count, 0) as anomaly_open_count')
            ->selectRaw('coalesce(production_batch_anomalies.total_count, 0) as anomaly_total_count')
            ->selectRaw('production_batch_anomalies.latest_open_detected_at as anomaly_latest_open_detected_at')
            ->selectRaw('production_batch_anomalies.latest_detected_at as anomaly_latest_detected_at')
            ->when($filters['product_id'] ?? null, fn ($query, $productId) => $query->where('product_id', $productId))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['cancel_reason'] ?? null, fn ($query, $cancelReason) => $query->where('cancel_reason', 'like', '%'.$cancelReason.'%'))
            ->when(array_key_exists('has_open_anomaly', $filters), function ($query) use ($filters) {
                if ((string) $filters['has_open_anomaly'] === '1') {
                    $query->whereRaw('coalesce(production_batch_anomalies.open_count, 0) > 0');

                    return;
                }

                $query->whereRaw('coalesce(production_batch_anomalies.open_count, 0) = 0');
            })
            ->when($filters['branch_id'] ?? null, fn ($query, $branchId) => $query->where('branch_id', $branchId))
            ->when($filters['location_id'] ?? null, fn ($query, $locationId) => $query->where('location_id', $locationId))
            ->when($filters['transaction_date_from'] ?? null, fn ($query, $dateFrom) => $query->whereDate('transaction_date', '>=', $dateFrom))
            ->when($filters['transaction_date_to'] ?? null, fn ($query, $dateTo) => $query->whereDate('transaction_date', '<=', $dateTo))
            ->tap(fn ($query) => $this->applyIndexSorting($query, $sortBy))
            ->limit($limit)
            ->get();

        $ids = $batches->pluck('id')->all();
        $lineStats = [];
        $anomalySummaries = [];

        if ($ids) {
            $rows = ProductionBatchItem::query()
                ->where('tenant_id', $tenantId)
                ->whereIn('production_batch_id', $ids)
                ->select('production_batch_id', 'line_type')
                ->selectRaw('count(*) as line_count')
                ->selectRaw('sum(planned_qty) as planned_qty_total')
                ->selectRaw('sum(coalesce(actual_qty, 0)) as actual_qty_total')
                ->groupBy('production_batch_id', 'line_type')
                ->get();

            foreach ($rows as $row) {
                $lineStats[$row->production_batch_id][$row->line_type] = [
                    'line_count' => (int) $row->line_count,
                    'planned_qty_total' => (float) $row->planned_qty_total,
                    'actual_qty_total' => (float) $row->actual_qty_total,
                ];
            }

            $anomalySummaries = $this->productionBatchAnomalySummaries($tenantId, $ids);
        }

        $payload = $batches->map(function ($batch) use ($lineStats, $anomalySummaries) {
            $input = $lineStats[$batch->id]['input'] ?? ['line_count' => 0, 'planned_qty_total' => 0.0, 'actual_qty_total' => 0.0];
            $output = $lineStats[$batch->id]['output'] ?? ['line_count' => 0, 'planned_qty_total' => 0.0, 'actual_qty_total' => 0.0];

            return [
                'id' => $batch->id,
                'branch_id' => $batch->branch_id,
                'location_id' => $batch->location_id,
                'product_id' => $batch->product_id,
                'product_variant_id' => $batch->product_variant_id,
                'reference_no' => $batch->reference_no,
                'status' => $batch->status,
                'transaction_date' => optional($batch->transaction_date)->format('Y-m-d'),
                'recipe_version' => $batch->recipe_version,
                'recipe_count' => $batch->recipe_version,
                'expected_output_qty' => (float) ($batch->expected_output_qty ?? 0),
                'actual_output_qty' => (float) ($batch->actual_output_qty ?? 0),
                'yield_variance_qty' => (float) ($batch->yield_variance_qty ?? 0),
                'yield_variance_pct' => (float) ($batch->yield_variance_pct ?? 0),
                'cancel_reason' => $batch->cancel_reason,
                'created_at' => $batch->created_at,
                'input_line_count' => $input['line_count'],
                'input_planned_qty_total' => $input['planned_qty_total'],
                'input_actual_qty_total' => $input['actual_qty_total'],
                'output_line_count' => $output['line_count'],
                'output_planned_qty_total' => $output['planned_qty_total'],
                'output_actual_qty_total' => $output['actual_qty_total'],
                'anomaly_summary' => $anomalySummaries[$batch->id] ?? $this->emptyProductionBatchAnomalySummary(),
                'observability_url' => $this->productionBatchObservabilityUrl((string) $batch->id),
            ];
        });

        return ApiResponse::success([
            'batches' => $payload,
        ], [
            'count' => $payload->count(),
            'limit' => $limit,
            'sort_by' => $sortBy,
            'kpis' => $this->buildBatchListKpis($payload),
            'filters' => array_filter([
                'product_id' => $filters['product_id'] ?? null,
                'sort_by' => $sortBy,
                'status' => $filters['status'] ?? null,
                'cancel_reason' => $filters['cancel_reason'] ?? null,
                'has_open_anomaly' => $filters['has_open_anomaly'] ?? null,
                'branch_id' => $filters['branch_id'] ?? null,
                'location_id' => $filters['location_id'] ?? null,
                'transaction_date_from' => $filters['transaction_date_from'] ?? null,
                'transaction_date_to' => $filters['transaction_date_to'] ?? null,
            ], static fn ($value) => $value !== null),
        ]);
    }

    public function store(Request $request, ProductionBatchService $service)
    {
        $tenantId = TenantContext::id();

        $payload = $request->validate([
            'branch_id' => [
                'required',
                'uuid',
                $tenantId
                    ? Rule::exists('branches', 'id')->where('tenant_id', $tenantId)
                    : 'exists:branches,id',
            ],
            'location_id' => [
                'required',
                'uuid',
                $tenantId
                    ? Rule::exists('locations', 'id')->where('tenant_id', $tenantId)
                    : 'exists:locations,id',
            ],
            'product_id' => [
                'required',
                'uuid',
                $tenantId
                    ? Rule::exists('products', 'id')->where('tenant_id', $tenantId)
                    : 'exists:products,id',
            ],
            'product_variant_id' => [
                'nullable',
                'uuid',
                $tenantId
                    ? Rule::exists('product_variants', 'id')->where('tenant_id', $tenantId)
                    : 'exists:product_variants,id',
            ],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'transaction_date' => ['required', 'date'],
            'expected_output_qty' => ['nullable', 'numeric', 'min:0.0001'],
            'recipe_version' => ['nullable', 'integer', 'min:1'],
            'recipe_count' => ['nullable', 'integer', 'min:1'],
        ]);

        if (! array_key_exists('recipe_count', $payload) && ! array_key_exists('recipe_version', $payload)) {
            throw ValidationException::withMessages([
                'recipe_count' => 'Jumlah resep wajib diisi.',
            ]);
        }

        try {
            $batch = $service->createDraft($payload, $request->user()?->id);

            return response()->json([
                'batch_id' => $batch->id,
                'status' => ProductionBatch::STATUS_DRAFT,
                'expected_output_qty' => (float) ($batch->expected_output_qty ?? 0),
                'recipe_version' => $batch->recipe_version,
                'recipe_count' => $batch->recipe_version,
            ], 201);
        } catch (ValidationException $exception) {
            return response()->json([
                'code' => 'VALIDATION_ERROR',
                'message' => 'Data tidak valid.',
                'details' => $exception->errors(),
            ], 422);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'code' => 'INVALID_TRANSACTION_DATE',
                'message' => $exception->getMessage(),
                'details' => [],
            ], 422);
        } catch (RuntimeException $exception) {
            return response()->json([
                'code' => 'PRODUCTION_BATCH_CREATE_FAILED',
                'message' => $exception->getMessage(),
                'details' => [],
            ], 422);
        }
    }

    public function show(string $batch)
    {
        $tenantId = TenantContext::id();

        $filters = request()->validate([
            'anomaly_status' => ['nullable', Rule::in(['all', 'open', 'resolved'])],
            'anomaly_limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'anomaly_offset' => ['nullable', 'integer', 'min:0', 'max:500'],
        ]);

        $anomalyStatus = (string) ($filters['anomaly_status'] ?? 'all');
        $anomalyLimit = (int) ($filters['anomaly_limit'] ?? 20);
        $anomalyOffset = (int) ($filters['anomaly_offset'] ?? 0);

        $batchRow = ProductionBatch::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $batch)
            ->firstOrFail();

        $items = ProductionBatchItem::query()
            ->leftJoin('products', function ($join) {
                $join->on('products.id', '=', 'production_batch_items.product_id')
                    ->on('products.tenant_id', '=', 'production_batch_items.tenant_id');
            })
            ->where('production_batch_items.tenant_id', $tenantId)
            ->where('production_batch_items.production_batch_id', $batchRow->id)
            ->select([
                'production_batch_items.id',
                'production_batch_items.line_type',
                'production_batch_items.qty',
                'production_batch_items.planned_qty',
                'production_batch_items.actual_qty',
                'production_batch_items.uom',
                'production_batch_items.unit_cost_snapshot',
                'production_batch_items.product_id',
                'production_batch_items.product_variant_id',
                'products.name as product_name',
                'products.sku as product_sku',
            ])
            ->get();

        $anomalySummary = $this->productionBatchAnomalySummaries($tenantId, [(string) $batchRow->id])[(string) $batchRow->id]
            ?? $this->emptyProductionBatchAnomalySummary();
        $anomalyHistory = $this->productionBatchAnomalyHistory($tenantId, (string) $batchRow->id, $anomalyStatus, $anomalyLimit, $anomalyOffset);
        $anomalyHistoryCount = $this->productionBatchAnomalyHistoryCount($tenantId, (string) $batchRow->id, $anomalyStatus);

        return response()->json([
            'batch' => [
                'id' => $batchRow->id,
                'branch_id' => $batchRow->branch_id,
                'location_id' => $batchRow->location_id,
                'product_id' => $batchRow->product_id,
                'product_variant_id' => $batchRow->product_variant_id,
                'reference_no' => $batchRow->reference_no,
                'status' => $batchRow->status,
                'notes' => $batchRow->notes,
                'transaction_date' => $batchRow->transaction_date,
                'recipe_version' => $batchRow->recipe_version,
                'recipe_count' => $batchRow->recipe_version,
                'expected_output_qty' => (float) ($batchRow->expected_output_qty ?? 0),
                'actual_output_qty' => (float) ($batchRow->actual_output_qty ?? 0),
                'yield_variance_qty' => (float) ($batchRow->yield_variance_qty ?? 0),
                'yield_variance_pct' => (float) ($batchRow->yield_variance_pct ?? 0),
                'variance_reason' => $batchRow->variance_reason,
                'cancel_reason' => $batchRow->cancel_reason,
                'created_by' => $batchRow->created_by,
                'posted_by' => $batchRow->posted_by,
                'created_at' => $batchRow->created_at,
                'posted_at' => $batchRow->posted_at,
            ],
            'inputs' => $items->where('line_type', 'input')->values(),
            'outputs' => $items->where('line_type', 'output')->values(),
            'anomaly_summary' => $anomalySummary,
            'anomaly_history' => $anomalyHistory,
            'anomaly_history_meta' => [
                'status' => $anomalyStatus,
                'limit' => $anomalyLimit,
                'offset' => $anomalyOffset,
                'count' => count($anomalyHistory),
                'total' => $anomalyHistoryCount,
                'has_more' => ($anomalyOffset + count($anomalyHistory)) < $anomalyHistoryCount,
                'next_offset' => ($anomalyOffset + count($anomalyHistory)) < $anomalyHistoryCount
                    ? $anomalyOffset + count($anomalyHistory)
                    : null,
            ],
            'observability_url' => $this->productionBatchObservabilityUrl((string) $batchRow->id),
        ]);
    }

    public function recipePreview(Request $request, ProductionEligibilityService $eligibility)
    {
        $tenantId = TenantContext::id();

        $payload = $request->validate([
            'product_id' => ['required', 'uuid'],
            'product_variant_id' => ['nullable', 'uuid'],
            'qty' => ['nullable', 'numeric', 'min:0.0001'],
            'recipe_count' => ['nullable', 'numeric', 'min:0.0001'],
            'location_id' => ['required', 'uuid'],
        ]);

        $recipeCount = $payload['recipe_count'] ?? $payload['qty'] ?? null;
        if ($recipeCount === null) {
            throw ValidationException::withMessages([
                'recipe_count' => 'Jumlah resep wajib diisi.',
            ]);
        }

        return ApiResponse::success(
            $eligibility->inspectRecipePreview(
                $tenantId,
                (string) $payload['product_id'],
                (string) $payload['location_id'],
                $recipeCount
            )
        );
    }

    public function exportProblematicCsv(Request $request): StreamedResponse
    {
        $tenantId = TenantContext::id();

        $filters = $request->validate([
            'product_id' => ['nullable', 'uuid'],
            'status' => ['nullable', Rule::in([
                ProductionBatch::STATUS_DRAFT,
                ProductionBatch::STATUS_POSTED,
                ProductionBatch::STATUS_CANCELLED,
            ])],
            'cancel_reason' => ['nullable', 'string', 'max:2000'],
            'has_open_anomaly' => ['nullable', 'in:0,1'],
            'branch_id' => ['nullable', 'uuid'],
            'location_id' => ['nullable', 'uuid'],
            'transaction_date_from' => ['nullable', 'date_format:Y-m-d'],
            'transaction_date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:transaction_date_from'],
        ]);

        $anomalySummarySubquery = Anomaly::query()
            ->where('tenant_id', $tenantId)
            ->where('type', 'production_batch_yield_variance')
            ->select('reference_id')
            ->selectRaw('count(*) as total_count')
            ->selectRaw('sum(case when resolved_at is null then 1 else 0 end) as open_count')
            ->selectRaw('max(case when resolved_at is null then detected_at else null end) as latest_open_detected_at')
            ->groupBy('reference_id');

        $rows = ProductionBatch::query()
            ->leftJoinSub($anomalySummarySubquery, 'production_batch_anomalies', function ($join) {
                $join->on('production_batch_anomalies.reference_id', '=', 'production_batches.id');
            })
            ->leftJoin('products', function ($join) {
                $join->on('products.id', '=', 'production_batches.product_id')
                    ->on('products.tenant_id', '=', 'production_batches.tenant_id');
            })
            ->leftJoin('branches', function ($join) {
                $join->on('branches.id', '=', 'production_batches.branch_id')
                    ->on('branches.tenant_id', '=', 'production_batches.tenant_id');
            })
            ->leftJoin('locations', function ($join) {
                $join->on('locations.id', '=', 'production_batches.location_id')
                    ->on('locations.tenant_id', '=', 'production_batches.tenant_id');
            })
            ->where('production_batches.tenant_id', $tenantId)
            ->where(function ($query) {
                $query->whereRaw('coalesce(production_batch_anomalies.open_count, 0) > 0')
                    ->orWhereRaw('abs(coalesce(production_batches.yield_variance_qty, 0)) > 0')
                    ->orWhere('production_batches.status', ProductionBatch::STATUS_CANCELLED);
            })
            ->when($filters['product_id'] ?? null, fn ($query, $productId) => $query->where('production_batches.product_id', $productId))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('production_batches.status', $status))
            ->when($filters['cancel_reason'] ?? null, fn ($query, $cancelReason) => $query->where('production_batches.cancel_reason', 'like', '%'.$cancelReason.'%'))
            ->when(array_key_exists('has_open_anomaly', $filters), function ($query) use ($filters) {
                if ((string) $filters['has_open_anomaly'] === '1') {
                    $query->whereRaw('coalesce(production_batch_anomalies.open_count, 0) > 0');

                    return;
                }

                $query->whereRaw('coalesce(production_batch_anomalies.open_count, 0) = 0');
            })
            ->when($filters['branch_id'] ?? null, fn ($query, $branchId) => $query->where('production_batches.branch_id', $branchId))
            ->when($filters['location_id'] ?? null, fn ($query, $locationId) => $query->where('production_batches.location_id', $locationId))
            ->when($filters['transaction_date_from'] ?? null, fn ($query, $dateFrom) => $query->whereDate('production_batches.transaction_date', '>=', $dateFrom))
            ->when($filters['transaction_date_to'] ?? null, fn ($query, $dateTo) => $query->whereDate('production_batches.transaction_date', '<=', $dateTo))
            ->orderByDesc(DB::raw('coalesce(production_batch_anomalies.open_count, 0)'))
            ->orderByRaw('abs(coalesce(production_batches.yield_variance_qty, 0)) desc')
            ->orderByDesc('production_batches.created_at')
            ->get([
                'production_batches.id',
                'production_batches.reference_no',
                'production_batches.status',
                'production_batches.transaction_date',
                'production_batches.expected_output_qty',
                'production_batches.actual_output_qty',
                'production_batches.yield_variance_qty',
                'production_batches.yield_variance_pct',
                'production_batches.variance_reason',
                'production_batches.cancel_reason',
                'products.name as product_name',
                'branches.name as branch_name',
                'locations.name as location_name',
                DB::raw('coalesce(production_batch_anomalies.total_count, 0) as anomaly_total_count'),
                DB::raw('coalesce(production_batch_anomalies.open_count, 0) as anomaly_open_count'),
                'production_batch_anomalies.latest_open_detected_at',
            ]);

        $filename = 'production-batches-problematic-'.now('UTC')->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, [
                'Batch ID',
                'Reference',
                'Product',
                'Branch',
                'Location',
                'Status',
                'Transaction Date',
                'Expected Output',
                'Actual Output',
                'Variance Qty',
                'Variance Pct',
                'Open Alerts',
                'Total Alerts',
                'Latest Open Alert',
                'Variance Reason',
                'Cancel Reason',
            ]);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    (string) $row->id,
                    (string) ($row->reference_no ?: $row->id),
                    (string) ($row->product_name ?: '-'),
                    (string) ($row->branch_name ?: '-'),
                    (string) ($row->location_name ?: '-'),
                    (string) ($row->status ?: '-'),
                    optional($row->transaction_date)->format('Y-m-d') ?: (string) $row->transaction_date,
                    (float) ($row->expected_output_qty ?? 0),
                    (float) ($row->actual_output_qty ?? 0),
                    (float) ($row->yield_variance_qty ?? 0),
                    (float) ($row->yield_variance_pct ?? 0),
                    (int) ($row->anomaly_open_count ?? 0),
                    (int) ($row->anomaly_total_count ?? 0),
                    $row->latest_open_detected_at,
                    (string) ($row->variance_reason ?: ''),
                    (string) ($row->cancel_reason ?: ''),
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function update(Request $request, string $batch, ProductionBatchService $service)
    {
        $tenantId = TenantContext::id();

        $payload = $request->validate([
            'product_variant_id' => [
                'nullable',
                'uuid',
                $tenantId
                    ? Rule::exists('product_variants', 'id')->where('tenant_id', $tenantId)
                    : 'exists:product_variants,id',
            ],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'transaction_date' => ['nullable', 'date'],
            'expected_output_qty' => ['nullable', 'numeric', 'min:0.0001'],
            'recipe_version' => ['nullable', 'integer', 'min:1'],
            'recipe_count' => ['nullable', 'integer', 'min:1'],
            'inputs' => ['nullable', 'array', 'min:1'],
            'inputs.*.product_id' => ['required_with:inputs', 'uuid'],
            'inputs.*.product_variant_id' => ['nullable', 'uuid'],
            'inputs.*.planned_qty' => ['required_with:inputs', 'numeric', 'min:0.0001'],
        ]);

        try {
            return response()->json($service->updateDraft($batch, $payload, $request->user()?->id));
        } catch (ModelNotFoundException) {
            abort(404);
        } catch (ValidationException $exception) {
            return response()->json([
                'code' => 'VALIDATION_ERROR',
                'message' => 'Data tidak valid.',
                'details' => $exception->errors(),
            ], 422);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'code' => 'INVALID_TRANSACTION_DATE',
                'message' => $exception->getMessage(),
                'details' => [],
            ], 422);
        } catch (RuntimeException $exception) {
            return response()->json([
                'code' => 'PRODUCTION_BATCH_UPDATE_FAILED',
                'message' => $exception->getMessage(),
                'details' => [],
            ], 422);
        }
    }

    public function post(Request $request, string $batch, ProductionBatchService $service)
    {
        $payload = $request->validate([
            'transaction_date' => ['nullable', 'date'],
            'actual_output_qty' => ['required', 'numeric', 'min:0.0001'],
            'variance_reason' => ['nullable', 'string', 'max:2000'],
            'inputs' => ['required', 'array', 'min:1'],
            'inputs.*.product_id' => ['required', 'uuid'],
            'inputs.*.product_variant_id' => ['nullable', 'uuid'],
            'inputs.*.actual_qty' => ['required', 'numeric', 'min:0'],
        ]);

        $idempotencyKey = $request->header('Idempotency-Key') ?? $request->header('X-Idempotency-Key');
        $idempotencyKey = is_string($idempotencyKey) ? trim($idempotencyKey) : '';

        try {
            $result = $service->post($batch, $payload, $request->user()?->id, $idempotencyKey);

            return response()->json($result, $result['idempotent'] ? 200 : 200);
        } catch (ModelNotFoundException) {
            abort(404);
        } catch (LockPeriodException $exception) {
            return response()->json([
                'code' => $exception->errorCode(),
                'message' => $exception->getMessage(),
                'details' => [],
            ], $exception->status());
        } catch (InvalidArgumentException $exception) {
            $tenantId = TenantContext::id();
            if ($tenantId) {
                app(AnomalyService::class)->raise(
                    tenantId: $tenantId,
                    severity: 'low',
                    type: 'invalid_transaction_date_attempt',
                    payload: [
                        'source' => 'production_batch_post',
                        'message' => $exception->getMessage(),
                    ]
                );
            }

            return response()->json([
                'code' => 'INVALID_TRANSACTION_DATE',
                'message' => $exception->getMessage(),
                'details' => [],
            ], 422);
        } catch (ValidationException $exception) {
            return response()->json([
                'code' => 'VALIDATION_ERROR',
                'message' => 'Data tidak valid.',
                'details' => $exception->errors(),
            ], 422);
        } catch (RuntimeException $exception) {
            return response()->json([
                'code' => 'PRODUCTION_BATCH_POST_FAILED',
                'message' => $exception->getMessage(),
                'details' => [],
            ], 422);
        }
    }

    public function cancel(Request $request, string $batch, ProductionBatchService $service)
    {
        try {
            return response()->json($service->cancelDraft($batch, $request->all(), $request->user()?->id));
        } catch (ModelNotFoundException) {
            abort(404);
        } catch (ValidationException $exception) {
            return response()->json([
                'code' => 'VALIDATION_ERROR',
                'message' => 'Data tidak valid.',
                'details' => $exception->errors(),
            ], 422);
        } catch (RuntimeException $exception) {
            return response()->json([
                'code' => 'PRODUCTION_BATCH_CANCEL_FAILED',
                'message' => $exception->getMessage(),
                'details' => [],
            ], 422);
        }
    }

    private function productionBatchAnomalySummaries(string $tenantId, array $referenceIds): array
    {
        if ($referenceIds === []) {
            return [];
        }

        $rows = Anomaly::query()
            ->where('tenant_id', $tenantId)
            ->where('type', 'production_batch_yield_variance')
            ->whereIn('reference_id', $referenceIds)
            ->select('reference_id')
            ->selectRaw('count(*) as total_count')
            ->selectRaw('sum(case when resolved_at is null then 1 else 0 end) as open_count')
            ->selectRaw('sum(case when resolved_at is not null then 1 else 0 end) as resolved_count')
            ->selectRaw('max(case when resolved_at is null then detected_at else null end) as latest_open_detected_at')
            ->groupBy('reference_id')
            ->get();

        $summary = [];

        foreach ($rows as $row) {
            $openCount = (int) ($row->open_count ?? 0);

            $summary[(string) $row->reference_id] = [
                'type' => 'production_batch_yield_variance',
                'total_count' => (int) ($row->total_count ?? 0),
                'open_count' => $openCount,
                'resolved_count' => (int) ($row->resolved_count ?? 0),
                'has_open_alert' => $openCount > 0,
                'latest_open_detected_at' => $row->latest_open_detected_at,
            ];
        }

        return $summary;
    }

    private function productionBatchAnomalyHistory(
        string $tenantId,
        string $batchId,
        string $status = 'all',
        int $limit = 20,
        int $offset = 0
    ): array
    {
        return Anomaly::query()
            ->where('tenant_id', $tenantId)
            ->where('type', 'production_batch_yield_variance')
            ->where('reference_id', $batchId)
            ->when($status === 'open', fn ($query) => $query->whereNull('resolved_at'))
            ->when($status === 'resolved', fn ($query) => $query->whereNotNull('resolved_at'))
            ->orderByDesc('detected_at')
            ->offset($offset)
            ->limit($limit)
            ->get([
                'id',
                'severity',
                'type',
                'reference_id',
                'payload_json',
                'detected_at',
                'resolved_at',
                'resolved_by',
            ])
            ->map(function (Anomaly $row) {
                $payload = is_array($row->payload_json) ? $row->payload_json : [];

                return [
                    'id' => (string) $row->id,
                    'severity' => (string) $row->severity,
                    'type' => (string) $row->type,
                    'reference_id' => $row->reference_id,
                    'status' => $row->resolved_at ? 'resolved' : 'open',
                    'payload_json' => $payload,
                    'branch_id' => $payload['branch_id'] ?? null,
                    'location_id' => $payload['location_id'] ?? null,
                    'detected_at' => $row->detected_at,
                    'resolved_at' => $row->resolved_at,
                    'resolved_by' => $row->resolved_by,
                    'resolved_note' => $payload['resolved_note'] ?? null,
                ];
            })
            ->values()
            ->all();
    }

    private function productionBatchAnomalyHistoryCount(string $tenantId, string $batchId, string $status = 'all'): int
    {
        return Anomaly::query()
            ->where('tenant_id', $tenantId)
            ->where('type', 'production_batch_yield_variance')
            ->where('reference_id', $batchId)
            ->when($status === 'open', fn ($query) => $query->whereNull('resolved_at'))
            ->when($status === 'resolved', fn ($query) => $query->whereNotNull('resolved_at'))
            ->count();
    }

    private function emptyProductionBatchAnomalySummary(): array
    {
        return [
            'type' => 'production_batch_yield_variance',
            'total_count' => 0,
            'open_count' => 0,
            'resolved_count' => 0,
            'has_open_alert' => false,
            'latest_open_detected_at' => null,
        ];
    }

    private function productionBatchObservabilityUrl(string $batchId): string
    {
        return '/backoffice/settings/observability?'.http_build_query([
            'type' => 'production_batch_yield_variance',
            'resolved' => '0',
            'reference_id' => $batchId,
        ]);
    }

    private function applyIndexSorting($query, string $sortBy): void
    {
        match ($sortBy) {
            'oldest' => $query->orderBy('production_batches.created_at')->orderBy('production_batches.id'),
            'largest_variance' => $query
                ->orderByRaw('abs(coalesce(production_batches.yield_variance_qty, 0)) desc')
                ->orderByDesc('production_batches.created_at'),
            'latest_anomaly' => $query
                ->orderByRaw('case when production_batch_anomalies.latest_detected_at is null then 1 else 0 end asc')
                ->orderByDesc(DB::raw('production_batch_anomalies.latest_detected_at'))
                ->orderByDesc('production_batches.created_at'),
            default => $query->orderByDesc('production_batches.created_at')->orderByDesc('production_batches.id'),
        };
    }

    private function buildBatchListKpis($payload): array
    {
        $batches = collect($payload);

        return [
            'total_batches' => $batches->count(),
            'draft_batches' => $batches->where('status', ProductionBatch::STATUS_DRAFT)->count(),
            'posted_batches' => $batches->where('status', ProductionBatch::STATUS_POSTED)->count(),
            'cancelled_batches' => $batches->where('status', ProductionBatch::STATUS_CANCELLED)->count(),
            'open_alert_batches' => $batches->filter(fn (array $row) => (bool) ($row['anomaly_summary']['has_open_alert'] ?? false))->count(),
            'variance_batch_count' => $batches->filter(fn (array $row) => abs((float) ($row['yield_variance_qty'] ?? 0)) > 0)->count(),
            'variance_qty_total' => round($batches->sum(fn (array $row) => abs((float) ($row['yield_variance_qty'] ?? 0))), 4),
        ];
    }
}
