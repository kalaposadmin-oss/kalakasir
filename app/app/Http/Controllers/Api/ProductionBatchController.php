<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\LockPeriodException;
use App\Http\Controllers\Controller;
use App\Models\ProductionBatch;
use App\Models\ProductionBatchItem;
use App\Services\AnomalyService;
use App\Services\ProductionBatchService;
use App\Support\ApiResponse;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;

class ProductionBatchController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = TenantContext::id();

        $limit = (int) $request->query('limit', 100);
        $limit = max(1, min($limit, 300));

        $batches = ProductionBatch::query()
            ->where('tenant_id', $tenantId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        $ids = $batches->pluck('id')->all();
        $lineStats = [];

        if ($ids) {
            $rows = ProductionBatchItem::query()
                ->where('tenant_id', $tenantId)
                ->whereIn('production_batch_id', $ids)
                ->select('production_batch_id', 'line_type')
                ->selectRaw('count(*) as line_count')
                ->selectRaw('sum(qty) as qty_total')
                ->groupBy('production_batch_id', 'line_type')
                ->get();

            foreach ($rows as $row) {
                $lineStats[$row->production_batch_id][$row->line_type] = [
                    'line_count' => (int) $row->line_count,
                    'qty_total' => (float) $row->qty_total,
                ];
            }
        }

        $payload = $batches->map(function ($batch) use ($lineStats) {
            $input = $lineStats[$batch->id]['input'] ?? ['line_count' => 0, 'qty_total' => 0.0];
            $output = $lineStats[$batch->id]['output'] ?? ['line_count' => 0, 'qty_total' => 0.0];

            return [
                'id' => $batch->id,
                'location_id' => $batch->location_id,
                'reference_no' => $batch->reference_no,
                'status' => $batch->status,
                'transaction_date' => $batch->transaction_date,
                'created_at' => $batch->created_at,
                'input_line_count' => $input['line_count'],
                'input_qty_total' => $input['qty_total'],
                'output_line_count' => $output['line_count'],
                'output_qty_total' => $output['qty_total'],
            ];
        });

        return ApiResponse::success([
            'batches' => $payload,
        ], [
            'count' => $payload->count(),
            'limit' => $limit,
        ]);
    }

    public function store(Request $request, ProductionBatchService $service)
    {
        $payload = $request->validate([
            'location_id' => ['required', 'uuid'],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'transaction_date' => ['nullable', 'date'],
            'inputs' => ['required', 'array', 'min:1'],
            'inputs.*.product_id' => ['required', 'uuid'],
            'inputs.*.qty' => ['required', 'numeric', 'min:0.01'],
            'outputs' => ['required', 'array', 'min:1'],
            'outputs.*.product_id' => ['required', 'uuid'],
            'outputs.*.qty' => ['required', 'numeric', 'min:0.01'],
        ]);

        try {
            $batchId = $service->createDraft($payload, $request->user()?->id);

            return response()->json([
                'batch_id' => $batchId,
                'status' => ProductionBatch::STATUS_DRAFT,
            ], 201);
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

        $batchRow = ProductionBatch::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $batch)
            ->firstOrFail();

        $items = ProductionBatchItem::query()
            ->join('products', 'products.id', '=', 'production_batch_items.product_id')
            ->where('production_batch_items.tenant_id', $tenantId)
            ->where('production_batch_items.production_batch_id', $batchRow->id)
            ->select([
                'production_batch_items.id',
                'production_batch_items.line_type',
                'production_batch_items.qty',
                'production_batch_items.unit_cost_snapshot',
                'products.id as product_id',
                'products.name as product_name',
                'products.sku as product_sku',
            ])
            ->get();

        return response()->json([
            'batch' => [
                'id' => $batchRow->id,
                'location_id' => $batchRow->location_id,
                'reference_no' => $batchRow->reference_no,
                'status' => $batchRow->status,
                'notes' => $batchRow->notes,
                'transaction_date' => $batchRow->transaction_date,
                'created_at' => $batchRow->created_at,
                'posted_at' => $batchRow->posted_at,
            ],
            'inputs' => $items->where('line_type', 'input')->values(),
            'outputs' => $items->where('line_type', 'output')->values(),
        ]);
    }

    public function post(Request $request, string $batch, ProductionBatchService $service)
    {
        $payload = $request->validate([
            'transaction_date' => ['nullable', 'date'],
        ]);

        try {
            $service->post($batch, $request->user()?->id, [
                'transaction_date' => $payload['transaction_date'] ?? null,
            ]);

            return response()->json([
                'status' => ProductionBatch::STATUS_POSTED,
            ]);
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
}