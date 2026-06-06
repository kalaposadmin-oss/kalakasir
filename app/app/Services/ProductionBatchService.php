<?php

namespace App\Services;

use App\Models\InventoryBalance;
use App\Models\Location;
use App\Models\ProductionBatch;
use App\Models\ProductionBatchItem;
use App\Models\Product;
use App\Models\StockLedger;
use App\Support\Decimal;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class ProductionBatchService
{
    public function createDraft(array $payload, ?string $userId): ProductionBatch
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            throw new RuntimeException('Tenant tidak ditemukan.');
        }

        $now = CarbonImmutable::now('UTC');
        $transactionDate = (new TransactionDateService())
            ->resolve(Arr::get($payload, 'transaction_date'));
        $location = $this->loadLocation($tenantId, (string) $payload['branch_id'], (string) $payload['location_id']);
        $product = $this->loadBatchProduct($tenantId, (string) $payload['product_id']);
        $productVariantId = $this->resolveVariantId($tenantId, (string) $product->id, Arr::get($payload, 'product_variant_id'));
        $recipeLines = $this->normalizeRecipeLines($product);
        $draftPlan = $this->resolveDraftPlan($payload, $product);
        $recipeCount = $draftPlan['recipe_count'];
        $plannedOutputs = $this->normalizeDraftOutputs($tenantId, $payload, $product, $productVariantId, $draftPlan);
        $expectedOutputQty = Decimal::qty(collect($plannedOutputs)->sum(fn (array $line) => (float) $line['planned_qty']));
        $plannedInputs = $this->buildPlannedInputsFromRecipeLines($recipeLines, $draftPlan['input_multiplier']);

        try {
            return DB::transaction(function () use ($payload, $tenantId, $userId, $now, $transactionDate, $location, $product, $productVariantId, $recipeLines, $expectedOutputQty, $recipeCount, $plannedInputs, $plannedOutputs): ProductionBatch {
                $batchId = (string) Str::uuid();

                $batch = ProductionBatch::query()->create([
                    'id' => $batchId,
                    'tenant_id' => $tenantId,
                    'branch_id' => $location->branch_id,
                    'location_id' => $payload['location_id'],
                    'product_id' => $product->id,
                    'product_variant_id' => $productVariantId,
                    'recipe_version' => $recipeCount,
                    'reference_no' => Arr::get($payload, 'reference_no'),
                    'status' => ProductionBatch::STATUS_DRAFT,
                    'notes' => Arr::get($payload, 'notes'),
                    'created_by' => $userId,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'transaction_date' => $transactionDate->toDateString(),
                    'expected_output_qty' => Decimal::float($expectedOutputQty, Decimal::QTY_SCALE),
                    'actual_output_qty' => 0,
                    'yield_variance_qty' => 0,
                    'yield_variance_pct' => 0,
                ]);

                $rows = [];

                foreach ($recipeLines as $line) {
                    $lineVariantId = $this->resolveVariantId($tenantId, (string) $line['product_id'], Arr::get($line, 'product_variant_id'));
                    $plannedQty = Decimal::qty($plannedInputs[$this->itemKey((string) $line['product_id'], $lineVariantId)] ?? 0);

                    $rows[] = [
                        'id' => (string) Str::uuid(),
                        'tenant_id' => $tenantId,
                        'production_batch_id' => $batchId,
                        'line_type' => 'input',
                        'product_id' => $line['product_id'],
                        'product_variant_id' => $lineVariantId,
                        'qty' => Decimal::float($plannedQty, Decimal::QTY_SCALE),
                        'planned_qty' => Decimal::float($plannedQty, Decimal::QTY_SCALE),
                        'actual_qty' => null,
                        'uom' => Arr::get($line, 'unit') ?: Arr::get($line, 'uom'),
                        'recipe_factor' => null,
                        'unit_cost_snapshot' => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                foreach ($plannedOutputs as $outputLine) {
                    $rows[] = [
                        'id' => (string) Str::uuid(),
                        'tenant_id' => $tenantId,
                        'production_batch_id' => $batchId,
                        'line_type' => 'output',
                        'product_id' => $outputLine['product_id'],
                        'product_variant_id' => $outputLine['product_variant_id'],
                        'qty' => Decimal::float($outputLine['planned_qty'], Decimal::QTY_SCALE),
                        'planned_qty' => Decimal::float($outputLine['planned_qty'], Decimal::QTY_SCALE),
                        'actual_qty' => null,
                        'uom' => null,
                        'recipe_factor' => $outputLine['recipe_factor'] !== null
                            ? Decimal::float($outputLine['recipe_factor'], Decimal::RECIPE_FACTOR_SCALE)
                            : null,
                        'unit_cost_snapshot' => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                ProductionBatchItem::query()->insert($rows);

                Log::info('production.batch.created', [
                    'batch_id' => $batchId,
                    'tenant_id' => $tenantId,
                    'location_id' => $payload['location_id'],
                    'total_input_cost' => 0.0,
                    'output_qty' => Decimal::float($expectedOutputQty, Decimal::QTY_SCALE),
                    'unit_cost_result' => 0.0,
                ]);

                $this->logBatchAudit(
                    event: 'production_batch.created',
                    tenantId: $tenantId,
                    actorId: $userId,
                    batchId: $batchId,
                    payload: [
                        'status' => ProductionBatch::STATUS_DRAFT,
                        'branch_id' => (string) $location->branch_id,
                        'location_id' => (string) $location->id,
                        'product_id' => (string) $product->id,
                        'product_variant_id' => $productVariantId,
                        'transaction_date' => $transactionDate->toDateString(),
                        'expected_output_qty' => Decimal::float($expectedOutputQty, Decimal::QTY_SCALE),
                        'recipe_version' => $recipeCount,
                        'recipe_count' => $recipeCount,
                        'reference_no' => Arr::get($payload, 'reference_no'),
                        'notes' => Arr::get($payload, 'notes'),
                        'variance_reason' => null,
                        'cancel_reason' => null,
                        'input_count' => count($recipeLines),
                        'input_planned_qty_total' => Decimal::float(
                            collect($rows)
                                ->where('line_type', 'input')
                                ->sum('planned_qty'),
                            Decimal::QTY_SCALE
                        ),
                        'output_planned_qty' => Decimal::float($expectedOutputQty, Decimal::QTY_SCALE),
                        'output_count' => count($plannedOutputs),
                    ],
                    occurredAt: $now
                );

                return $batch;
            });
        } catch (Throwable $exception) {
            Log::warning('production.batch.failed', [
                'stage' => 'create',
                'batch_id' => null,
                'tenant_id' => $tenantId,
                'location_id' => Arr::get($payload, 'location_id'),
                'total_input_cost' => 0.0,
                'output_qty' => Decimal::float($expectedOutputQty, Decimal::QTY_SCALE),
                'unit_cost_result' => 0.0,
                'error' => $exception->getMessage(),
                'exception' => class_basename($exception),
            ]);

            throw $exception;
        }
    }

    public function updateDraft(string $batchId, array $payload, ?string $userId): array
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            throw new RuntimeException('Tenant tidak ditemukan.');
        }

        $now = CarbonImmutable::now('UTC');

        return app(DatabaseTransactionService::class)->run(function () use ($batchId, $payload, $tenantId, $userId, $now): array {
            $batch = ProductionBatch::query()
                ->where('tenant_id', $tenantId)
                ->where('id', $batchId)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertDraftMutable($batch);

            $product = $this->loadBatchProduct($tenantId, (string) $batch->product_id);
            $transactionDate = (new TransactionDateService())
                ->resolve(Arr::get($payload, 'transaction_date', $batch->transaction_date));
            $draftPlan = $this->resolveDraftPlan($payload, $product, $batch);
            $expectedOutputQty = $draftPlan['expected_output_qty'];
            $recipeCount = $draftPlan['recipe_count'];

            $productVariantId = array_key_exists('product_variant_id', $payload)
                ? $this->resolveVariantId($tenantId, (string) $product->id, Arr::get($payload, 'product_variant_id'))
                : ($batch->product_variant_id ? (string) $batch->product_variant_id : null);

            $items = ProductionBatchItem::query()
                ->where('tenant_id', $tenantId)
                ->where('production_batch_id', $batch->id)
                ->lockForUpdate()
                ->get();

            $inputs = $items->where('line_type', 'input')->values();
            $outputs = $items->where('line_type', 'output')->values();
            $output = $outputs->first();

            if ($inputs->isEmpty() || $outputs->isEmpty()) {
                throw ValidationException::withMessages([
                    'batch' => 'Batch harus memiliki minimal satu input dan satu output.',
                ]);
            }

            $beforeSnapshot = $this->draftAuditSnapshot($batch, $inputs, $outputs);

            $plannedInputMap = null;

            if ($draftPlan['mode'] === 'recipe_count') {
                $plannedInputMap = $this->buildPlannedInputsFromRecipeLines(
                    $this->normalizeRecipeLines($product),
                    $draftPlan['input_multiplier']
                );
            } elseif (array_key_exists('inputs', $payload)) {
                $plannedInputMap = $this->normalizePlannedInputs($payload['inputs'] ?? []);
            }

            if ($plannedInputMap !== null) {
                $this->validateDraftInputKeys($inputs, $plannedInputMap, 'inputs');
            }

            $previousExpectedOutputQty = Decimal::qty($batch->expected_output_qty);
            $scaleInputs = $plannedInputMap === null
                && Decimal::compare($expectedOutputQty, $previousExpectedOutputQty) !== 0;

            foreach ($inputs as $item) {
                $key = $this->itemKey((string) $item->product_id, $item->product_variant_id ? (string) $item->product_variant_id : null);

                if ($plannedInputMap !== null) {
                    $plannedQty = Decimal::qty($plannedInputMap[$key]);
                } elseif ($scaleInputs) {
                    $plannedQty = Decimal::compare($previousExpectedOutputQty, '0') > 0
                        ? Decimal::divide(
                            Decimal::multiply(Decimal::qty($item->planned_qty), $expectedOutputQty, Decimal::QTY_SCALE),
                            $previousExpectedOutputQty,
                            Decimal::QTY_SCALE
                        )
                        : Decimal::qty($item->planned_qty);
                } else {
                    $plannedQty = Decimal::qty($item->planned_qty);
                }

                if (Decimal::compare($plannedQty, '0') <= 0) {
                    throw ValidationException::withMessages([
                        'inputs' => 'Planned qty input harus lebih dari 0.',
                    ]);
                }

                $item->planned_qty = Decimal::float($plannedQty, Decimal::QTY_SCALE);
                $item->qty = Decimal::float($plannedQty, Decimal::QTY_SCALE);
                $item->actual_qty = null;
                $item->updated_at = $now;
                $item->save();
            }

            if (array_key_exists('outputs', $payload)) {
                $plannedOutputs = $this->normalizeDraftOutputs($tenantId, $payload, $product, $productVariantId, $draftPlan);
                $expectedOutputQty = Decimal::qty(collect($plannedOutputs)->sum(fn (array $line) => (float) $line['planned_qty']));

                ProductionBatchItem::query()
                    ->where('tenant_id', $tenantId)
                    ->where('production_batch_id', $batch->id)
                    ->where('line_type', 'output')
                    ->delete();

                foreach ($plannedOutputs as $outputLine) {
                    ProductionBatchItem::query()->create([
                        'id' => (string) Str::uuid(),
                        'tenant_id' => $tenantId,
                        'production_batch_id' => $batch->id,
                        'line_type' => 'output',
                        'product_id' => $outputLine['product_id'],
                        'product_variant_id' => $outputLine['product_variant_id'],
                        'qty' => Decimal::float($outputLine['planned_qty'], Decimal::QTY_SCALE),
                        'planned_qty' => Decimal::float($outputLine['planned_qty'], Decimal::QTY_SCALE),
                        'actual_qty' => null,
                        'uom' => null,
                        'recipe_factor' => $outputLine['recipe_factor'] !== null
                            ? Decimal::float($outputLine['recipe_factor'], Decimal::RECIPE_FACTOR_SCALE)
                            : null,
                        'unit_cost_snapshot' => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            } else {
                $output->product_variant_id = $productVariantId;
                $output->planned_qty = Decimal::float($expectedOutputQty, Decimal::QTY_SCALE);
                $output->qty = Decimal::float($expectedOutputQty, Decimal::QTY_SCALE);
                $output->actual_qty = null;
                $output->updated_at = $now;
                $output->save();
            }

            $batch->product_variant_id = $productVariantId;
            $batch->transaction_date = $transactionDate->toDateString();
            $batch->expected_output_qty = Decimal::float($expectedOutputQty, Decimal::QTY_SCALE);
            $batch->recipe_version = $recipeCount;
            if (array_key_exists('reference_no', $payload)) {
                $batch->reference_no = Arr::get($payload, 'reference_no');
            }
            if (array_key_exists('notes', $payload)) {
                $batch->notes = Arr::get($payload, 'notes');
            }
            $batch->updated_at = $now;
            $batch->save();

            $freshBatch = $batch->fresh();
            $freshInputs = $inputs->fresh();
            $freshOutputs = ProductionBatchItem::query()
                ->where('tenant_id', $tenantId)
                ->where('production_batch_id', $batch->id)
                ->where('line_type', 'output')
                ->get();

            $this->logBatchAudit(
                event: 'production_batch.updated',
                tenantId: $tenantId,
                actorId: $userId,
                batchId: (string) $batch->id,
                payload: [
                    'status' => ProductionBatch::STATUS_DRAFT,
                    'before' => $beforeSnapshot,
                    'after' => $this->draftAuditSnapshot($freshBatch, $freshInputs, $freshOutputs),
                ],
                occurredAt: $now
            );

            return $this->serializeDraftResult($freshBatch, $freshInputs, $freshOutputs);
        });
    }

    public function cancelDraft(string $batchId, array $payload, ?string $userId): array
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            throw new RuntimeException('Tenant tidak ditemukan.');
        }

        $now = CarbonImmutable::now('UTC');

        return app(DatabaseTransactionService::class)->run(function () use ($batchId, $payload, $tenantId, $userId, $now): array {
            $batch = ProductionBatch::query()
                ->where('tenant_id', $tenantId)
                ->where('id', $batchId)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertDraftMutable($batch);
            $cancelReason = $this->normalizeCancelReason(Arr::get($payload, 'cancel_reason'));

            $batch->status = ProductionBatch::STATUS_CANCELLED;
            $batch->cancel_reason = $cancelReason;
            $batch->cancelled_by = $userId;
            $batch->cancelled_at = $now;
            $batch->updated_at = $now;
            $batch->save();

            $this->logBatchAudit(
                event: 'production_batch.cancelled',
                tenantId: $tenantId,
                actorId: $userId,
                batchId: (string) $batch->id,
                payload: [
                    'status' => ProductionBatch::STATUS_CANCELLED,
                    'transaction_date' => optional($batch->transaction_date)?->format('Y-m-d'),
                    'expected_output_qty' => (float) ($batch->expected_output_qty ?? 0),
                    'reference_no' => $batch->reference_no,
                    'notes' => $batch->notes,
                    'variance_reason' => $batch->variance_reason,
                    'cancel_reason' => $cancelReason,
                    'cancelled_at' => optional($batch->cancelled_at)->toISOString(),
                ],
                occurredAt: $now
            );

            return [
                'batch_id' => (string) $batch->id,
                'status' => (string) $batch->status,
                'cancel_reason' => $batch->cancel_reason,
                'cancelled_at' => optional($batch->cancelled_at)->toISOString(),
            ];
        });
    }

    public function post(string $batchId, array $payload, ?string $userId, string $idempotencyKey): array
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            throw new RuntimeException('Tenant tidak ditemukan.');
        }

        $idempotencyKey = trim($idempotencyKey);
        if ($idempotencyKey === '') {
            throw ValidationException::withMessages([
                'Idempotency-Key' => 'Idempotency-Key wajib diisi.',
            ]);
        }

        $cacheKey = $this->idempotencyCacheKey($tenantId, $batchId, $idempotencyKey);

        $result = app(OperationIdempotencyStore::class)->getOrRemember($cacheKey, function () use ($batchId, $payload, $tenantId, $userId, $idempotencyKey): array {
            $now = CarbonImmutable::now('UTC');
            $observabilityContext = [
                'batch_id' => $batchId,
                'tenant_id' => $tenantId,
                'location_id' => null,
                'total_input_cost' => 0.0,
                'output_qty' => 0.0,
                'unit_cost_result' => 0.0,
            ];

            try {
                return app(DatabaseTransactionService::class)->run(function () use ($batchId, $payload, $tenantId, $userId, $idempotencyKey, $now, &$observabilityContext): array {
                    $valuationService = app(InventoryValuationService::class);

                    $batch = ProductionBatch::query()
                        ->where('tenant_id', $tenantId)
                        ->where('id', $batchId)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $this->loadBatchProduct($tenantId, (string) $batch->product_id);

                    $observabilityContext['location_id'] = $batch->location_id;

                    if ($batch->status === ProductionBatch::STATUS_POSTED) {
                        if ($batch->idempotency_key === $idempotencyKey) {
                            return $this->serializePostResult($batch, true);
                        }

                        throw ValidationException::withMessages([
                            'batch' => 'Batch already posted or cancelled.',
                        ]);
                    }

                    if ($batch->status !== ProductionBatch::STATUS_DRAFT) {
                        throw ValidationException::withMessages([
                            'batch' => 'Batch already posted or cancelled.',
                        ]);
                    }

                    $transactionDate = (new TransactionDateService())
                        ->resolve(Arr::get($payload, 'transaction_date', $batch->transaction_date));
                    (new LockPeriodService())->ensureNotLocked($transactionDate, $tenantId);

                    $items = ProductionBatchItem::query()
                        ->where('tenant_id', $tenantId)
                        ->where('production_batch_id', $batch->id)
                        ->lockForUpdate()
                        ->get();

                    $inputs = $items->where('line_type', 'input')->values();
                    $outputs = $items->where('line_type', 'output')->values();

                    if ($inputs->isEmpty() || $outputs->isEmpty()) {
                        throw ValidationException::withMessages([
                            'batch' => 'Batch must contain at least one input and one output item.',
                        ]);
                    }

                    $actualInputMap = $this->normalizeActualInputs($payload['inputs'] ?? []);
                    $actualOutputMap = $this->normalizeActualOutputs($payload, $outputs);
                    $actualOutputQty = Decimal::qty(array_sum($actualOutputMap));

                    $this->validatePostPayload(
                        $batch,
                        $inputs,
                        $outputs,
                        $actualInputMap,
                        $actualOutputMap,
                        $actualOutputQty,
                        Arr::get($payload, 'variance_reason')
                    );
                    $this->lockInventoryRows($tenantId, (string) $batch->location_id, $inputs, $outputs);

                    $ledgerRows = [];
                    $totalInputCost = Decimal::cost('0');

                    foreach ($inputs as $item) {
                        $actualQty = Decimal::qty($actualInputMap[$this->itemKey((string) $item->product_id, $item->product_variant_id ? (string) $item->product_variant_id : null)] ?? 0);
                        $productVariantId = $item->product_variant_id ? (string) $item->product_variant_id : null;

                        if (Decimal::compare($actualQty, '0') > 0) {
                            $affected = $this->decrementInventory(
                                $tenantId,
                                (string) $item->product_id,
                                $productVariantId,
                                (string) $batch->location_id,
                                Decimal::float($actualQty, Decimal::QTY_SCALE)
                            );

                            if ($affected === 0) {
                                app(AnomalyService::class)->raise(
                                    tenantId: $tenantId,
                                    severity: 'high',
                                    type: 'negative_stock_attempt',
                                    payload: [
                                        'source' => 'production_batch_post',
                                        'batch_id' => $batch->id,
                                        'product_id' => $item->product_id,
                                        'product_variant_id' => $productVariantId,
                                        'location_id' => $batch->location_id,
                                        'requested_qty' => Decimal::float($actualQty, Decimal::QTY_SCALE),
                                    ],
                                    referenceId: $batch->id
                                );

                                throw new RuntimeException('Stok bahan baku tidak mencukupi.');
                            }

                            $valuation = $valuationService->issue(
                                tenantId: $tenantId,
                                productId: (string) $item->product_id,
                                productVariantId: $productVariantId,
                                locationId: (string) $batch->location_id,
                                qty: Decimal::float($actualQty, Decimal::QTY_SCALE),
                                referenceType: 'production_batch',
                                referenceId: (string) $batch->id,
                                referenceItemId: (string) $item->id,
                                at: $now
                            );

                            $unitCost = Decimal::cost($valuation['unit_cost']);
                            $totalCost = Decimal::cost($valuation['total_cost']);
                            $totalInputCost = Decimal::add($totalInputCost, $totalCost, Decimal::COST_SCALE);

                            $balanceAfter = Decimal::qty($this->inventoryBalanceQuery(
                                tenantId: $tenantId,
                                productId: (string) $item->product_id,
                                productVariantId: $productVariantId,
                                locationId: (string) $batch->location_id,
                            )->value('qty_on_hand'));

                            $ledgerRows[] = [
                                'id' => (string) Str::uuid(),
                                'tenant_id' => $tenantId,
                                'product_id' => $item->product_id,
                                'product_variant_id' => $productVariantId,
                                'location_id' => $batch->location_id,
                                'reference_type' => 'production_batch',
                                'reference_id' => $batch->id,
                                'reference_item_id' => $item->id,
                                'qty_in' => 0,
                                'qty_out' => Decimal::float($actualQty, Decimal::QTY_SCALE),
                                'balance_after' => Decimal::float($balanceAfter, Decimal::QTY_SCALE),
                                'unit_cost' => Decimal::float($unitCost, Decimal::COST_SCALE),
                                'total_cost' => Decimal::float($totalCost, Decimal::COST_SCALE),
                                'created_at' => $now,
                                'created_by' => $userId,
                            ];

                            $item->unit_cost_snapshot = Decimal::float($unitCost, Decimal::COST_SCALE);
                        } else {
                            $item->unit_cost_snapshot = 0;
                        }

                        $item->actual_qty = Decimal::float($actualQty, Decimal::QTY_SCALE);
                        $item->qty = Decimal::float($actualQty, Decimal::QTY_SCALE);
                        $item->updated_at = $now;
                        $item->save();
                    }

                    $observabilityContext['total_input_cost'] = Decimal::float($totalInputCost, Decimal::COST_SCALE);
                    $observabilityContext['output_qty'] = Decimal::float($actualOutputQty, Decimal::QTY_SCALE);

                    $allocationBase = $this->outputAllocationBase($outputs, $actualOutputMap);
                    $weightedUnitCost = Decimal::compare($totalInputCost, '0') > 0 && Decimal::compare($allocationBase, '0') > 0
                        ? Decimal::divide($totalInputCost, $allocationBase, Decimal::COST_SCALE)
                        : Decimal::cost('0');
                    $observabilityContext['unit_cost_result'] = Decimal::float($weightedUnitCost, Decimal::COST_SCALE);

                    foreach ($outputs as $output) {
                        $outputKey = $this->itemKey((string) $output->product_id, $output->product_variant_id ? (string) $output->product_variant_id : null);
                        $lineActualQty = Decimal::qty($actualOutputMap[$outputKey] ?? 0);
                        $lineFactor = Decimal::recipeFactor($output->recipe_factor ?? 0);
                        $lineAllocationUnits = Decimal::compare($lineFactor, '0') > 0
                            ? Decimal::multiply($lineActualQty, $lineFactor, Decimal::QTY_SCALE)
                            : $lineActualQty;
                        $lineAllocatedCost = Decimal::multiply($weightedUnitCost, $lineAllocationUnits, Decimal::COST_SCALE);
                        $outputUnitCost = Decimal::compare($lineActualQty, '0') > 0
                            ? Decimal::divide($lineAllocatedCost, $lineActualQty, Decimal::COST_SCALE)
                            : Decimal::cost('0');
                        $outputVariantId = $output->product_variant_id ? (string) $output->product_variant_id : null;

                        if (Decimal::compare($lineActualQty, '0') > 0) {
                            $this->ensureInventoryBalanceBucket($tenantId, (string) $output->product_id, $outputVariantId, (string) $batch->location_id);
                            $this->incrementInventory(
                                $tenantId,
                                (string) $output->product_id,
                                $outputVariantId,
                                (string) $batch->location_id,
                                Decimal::float($lineActualQty, Decimal::QTY_SCALE)
                            );

                            $valuationService->receive(
                                tenantId: $tenantId,
                                productId: (string) $output->product_id,
                                productVariantId: $outputVariantId,
                                locationId: (string) $batch->location_id,
                                qty: Decimal::float($lineActualQty, Decimal::QTY_SCALE),
                                unitCost: Decimal::float($outputUnitCost, Decimal::COST_SCALE),
                                sourceType: 'production_batch',
                                sourceId: (string) $batch->id,
                                sourceItemId: (string) $output->id,
                                at: $now
                            );

                            $outputBalanceAfter = Decimal::qty($this->inventoryBalanceQuery(
                                tenantId: $tenantId,
                                productId: (string) $output->product_id,
                                productVariantId: $outputVariantId,
                                locationId: (string) $batch->location_id,
                            )->value('qty_on_hand'));

                            $ledgerRows[] = [
                                'id' => (string) Str::uuid(),
                                'tenant_id' => $tenantId,
                                'product_id' => $output->product_id,
                                'product_variant_id' => $outputVariantId,
                                'location_id' => $batch->location_id,
                                'reference_type' => 'production_batch',
                                'reference_id' => $batch->id,
                                'reference_item_id' => $output->id,
                                'qty_in' => Decimal::float($lineActualQty, Decimal::QTY_SCALE),
                                'qty_out' => 0,
                                'balance_after' => Decimal::float($outputBalanceAfter, Decimal::QTY_SCALE),
                                'unit_cost' => Decimal::float($outputUnitCost, Decimal::COST_SCALE),
                                'total_cost' => Decimal::float($lineAllocatedCost, Decimal::COST_SCALE),
                                'created_at' => $now,
                                'created_by' => $userId,
                            ];
                        }

                        $output->actual_qty = Decimal::float($lineActualQty, Decimal::QTY_SCALE);
                        $output->qty = Decimal::float($lineActualQty, Decimal::QTY_SCALE);
                        $output->unit_cost_snapshot = Decimal::float($outputUnitCost, Decimal::COST_SCALE);
                        $output->updated_at = $now;
                        $output->save();
                    }

                    StockLedger::query()->insert($ledgerRows);

                    $expectedOutputQty = Decimal::qty($batch->expected_output_qty);
                    $yieldVarianceQty = Decimal::subtract($actualOutputQty, $expectedOutputQty, Decimal::QTY_SCALE);
                    $yieldVariancePct = Decimal::compare($expectedOutputQty, '0') > 0
                        ? Decimal::divide(
                            Decimal::multiply($yieldVarianceQty, Decimal::qty('100'), Decimal::QTY_SCALE),
                            $expectedOutputQty,
                            4
                        )
                        : '0';

                    $batch->actual_output_qty = Decimal::float($actualOutputQty, Decimal::QTY_SCALE);
                    $batch->yield_variance_qty = Decimal::float($yieldVarianceQty, Decimal::QTY_SCALE);
                    $batch->yield_variance_pct = Decimal::float($yieldVariancePct, 4);
                    $batch->variance_reason = $payload['variance_reason'] ?? null;
                    $batch->status = ProductionBatch::STATUS_POSTED;
                    $batch->posted_by = $userId;
                    $batch->posted_at = $now;
                    $batch->updated_at = $now;
                    $batch->transaction_date = $transactionDate->toDateString();
                    $batch->idempotency_key = $idempotencyKey;
                    $batch->save();

                    $freshBatch = $batch->fresh();
                    $freshInputs = $inputs->fresh();
                    $freshOutputs = $outputs->fresh();

                    $this->raiseYieldVarianceAnomalyIfNeeded($freshBatch, $tenantId, $freshInputs, $freshOutputs);

                    $this->logBatchAudit(
                        event: 'production_batch.posted',
                        tenantId: $tenantId,
                        actorId: $userId,
                        batchId: (string) $batch->id,
                        payload: $this->postAuditSnapshot($freshBatch, $freshInputs, $freshOutputs, $idempotencyKey),
                        occurredAt: $now
                    );

                    Log::info('production.batch.posted', $observabilityContext);

                    return $this->serializePostResult($freshBatch, false);
                });
            } catch (Throwable $exception) {
                Log::warning('production.batch.failed', array_merge($observabilityContext, [
                    'stage' => 'post',
                    'error' => $exception->getMessage(),
                    'exception' => class_basename($exception),
                ]));

                throw $exception;
            }
        });

        return [
            ...$result['value'],
            'idempotent' => (bool) ($result['replayed'] || ($result['value']['idempotent'] ?? false)),
        ];
    }

    public function correctPostedTransactionDate(string $batchId, array $payload, ?string $userId): array
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            throw new RuntimeException('Tenant tidak ditemukan.');
        }

        $reason = trim((string) Arr::get($payload, 'reason', ''));
        if (mb_strlen($reason) < 10) {
            throw ValidationException::withMessages([
                'reason' => 'Alasan koreksi tanggal wajib diisi minimal 10 karakter.',
            ]);
        }

        $transactionDate = (new TransactionDateService())
            ->resolve(Arr::get($payload, 'transaction_date'));

        return app(DatabaseTransactionService::class)->run(function () use ($batchId, $tenantId, $userId, $transactionDate, $reason): array {
            $batch = ProductionBatch::query()
                ->where('tenant_id', $tenantId)
                ->where('id', $batchId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($batch->status !== ProductionBatch::STATUS_POSTED) {
                throw ValidationException::withMessages([
                    'batch' => 'Koreksi tanggal hanya bisa untuk batch yang sudah diposting.',
                ]);
            }

            $oldDate = optional($batch->transaction_date)?->format('Y-m-d');
            $newDate = $transactionDate->toDateString();

            (new LockPeriodService())->ensureNotLocked($transactionDate, $tenantId);
            if ($oldDate) {
                (new LockPeriodService())->ensureNotLocked(CarbonImmutable::parse($oldDate), $tenantId);
            }

            if ($oldDate === $newDate) {
                return [
                    'batch_id' => (string) $batch->id,
                    'status' => (string) $batch->status,
                    'transaction_date' => $newDate,
                    'changed' => false,
                ];
            }

            $batch->transaction_date = $newDate;
            $batch->updated_at = CarbonImmutable::now('UTC');
            $batch->save();

            $this->logBatchAudit(
                event: 'production_batch.transaction_date_corrected',
                tenantId: $tenantId,
                actorId: $userId,
                batchId: (string) $batch->id,
                payload: [
                    'before' => ['transaction_date' => $oldDate],
                    'after' => ['transaction_date' => $newDate],
                    'reason' => $reason,
                ],
                occurredAt: CarbonImmutable::now('UTC')
            );

            return [
                'batch_id' => (string) $batch->id,
                'status' => (string) $batch->status,
                'transaction_date' => $newDate,
                'changed' => true,
            ];
        });
    }

    public function voidPostedBatch(string $batchId, array $payload, ?string $userId): array
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            throw new RuntimeException('Tenant tidak ditemukan.');
        }

        $reason = trim((string) Arr::get($payload, 'reason', ''));
        if (mb_strlen($reason) < 10) {
            throw ValidationException::withMessages([
                'reason' => 'Alasan void posting wajib diisi minimal 10 karakter.',
            ]);
        }

        return app(DatabaseTransactionService::class)->run(function () use ($batchId, $tenantId, $userId, $reason): array {
            $now = CarbonImmutable::now('UTC');
            $valuationService = app(InventoryValuationService::class);

            $batch = ProductionBatch::query()
                ->where('tenant_id', $tenantId)
                ->where('id', $batchId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($batch->status !== ProductionBatch::STATUS_POSTED) {
                throw ValidationException::withMessages([
                    'batch' => 'Void posting hanya bisa untuk batch yang sudah diposting.',
                ]);
            }

            (new LockPeriodService())->ensureNotLocked(CarbonImmutable::parse((string) $batch->transaction_date), $tenantId);

            $items = ProductionBatchItem::query()
                ->where('tenant_id', $tenantId)
                ->where('production_batch_id', $batch->id)
                ->lockForUpdate()
                ->get();
            $inputs = $items->where('line_type', 'input')->values();
            $outputs = $items->where('line_type', 'output')->values();
            $ledgerRows = [];

            $this->lockInventoryRows($tenantId, (string) $batch->location_id, $inputs, $outputs);

            foreach ($outputs as $output) {
                $qty = Decimal::qty($output->actual_qty ?? 0);
                if (Decimal::compare($qty, '0') <= 0) {
                    continue;
                }

                $variantId = $output->product_variant_id ? (string) $output->product_variant_id : null;
                $affected = $this->decrementInventory(
                    $tenantId,
                    (string) $output->product_id,
                    $variantId,
                    (string) $batch->location_id,
                    Decimal::float($qty, Decimal::QTY_SCALE)
                );

                if ($affected === 0) {
                    throw ValidationException::withMessages([
                        'batch' => 'Stok produk jadi tidak cukup untuk void posting. Produk hasil batch kemungkinan sudah terjual atau terpakai.',
                    ]);
                }

                $valuation = $valuationService->issue(
                    tenantId: $tenantId,
                    productId: (string) $output->product_id,
                    productVariantId: $variantId,
                    locationId: (string) $batch->location_id,
                    qty: Decimal::float($qty, Decimal::QTY_SCALE),
                    referenceType: 'production_batch_void',
                    referenceId: (string) $batch->id,
                    referenceItemId: (string) $output->id,
                    at: $now
                );

                $balanceAfter = Decimal::qty($this->inventoryBalanceQuery(
                    tenantId: $tenantId,
                    productId: (string) $output->product_id,
                    productVariantId: $variantId,
                    locationId: (string) $batch->location_id,
                )->value('qty_on_hand'));

                $ledgerRows[] = [
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'product_id' => $output->product_id,
                    'product_variant_id' => $variantId,
                    'location_id' => $batch->location_id,
                    'reference_type' => 'production_batch_void',
                    'reference_id' => $batch->id,
                    'reference_item_id' => $output->id,
                    'qty_in' => 0,
                    'qty_out' => Decimal::float($qty, Decimal::QTY_SCALE),
                    'balance_after' => Decimal::float($balanceAfter, Decimal::QTY_SCALE),
                    'unit_cost' => Decimal::float($valuation['unit_cost'] ?? 0, Decimal::COST_SCALE),
                    'total_cost' => Decimal::float($valuation['total_cost'] ?? 0, Decimal::COST_SCALE),
                    'created_at' => $now,
                    'created_by' => $userId,
                ];
            }

            foreach ($inputs as $input) {
                $qty = Decimal::qty($input->actual_qty ?? 0);
                if (Decimal::compare($qty, '0') <= 0) {
                    continue;
                }

                $variantId = $input->product_variant_id ? (string) $input->product_variant_id : null;
                $this->incrementInventory(
                    $tenantId,
                    (string) $input->product_id,
                    $variantId,
                    (string) $batch->location_id,
                    Decimal::float($qty, Decimal::QTY_SCALE)
                );

                $balanceAfter = Decimal::qty($this->inventoryBalanceQuery(
                    tenantId: $tenantId,
                    productId: (string) $input->product_id,
                    productVariantId: $variantId,
                    locationId: (string) $batch->location_id,
                )->value('qty_on_hand'));
                $unitCost = Decimal::cost($input->unit_cost_snapshot ?? 0);
                $totalCost = Decimal::multiply($qty, $unitCost, Decimal::COST_SCALE);

                $ledgerRows[] = [
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'product_id' => $input->product_id,
                    'product_variant_id' => $variantId,
                    'location_id' => $batch->location_id,
                    'reference_type' => 'production_batch_void',
                    'reference_id' => $batch->id,
                    'reference_item_id' => $input->id,
                    'qty_in' => Decimal::float($qty, Decimal::QTY_SCALE),
                    'qty_out' => 0,
                    'balance_after' => Decimal::float($balanceAfter, Decimal::QTY_SCALE),
                    'unit_cost' => Decimal::float($unitCost, Decimal::COST_SCALE),
                    'total_cost' => Decimal::float($totalCost, Decimal::COST_SCALE),
                    'created_at' => $now,
                    'created_by' => $userId,
                ];
            }

            $valuationService->reverseIssue(
                tenantId: $tenantId,
                referenceType: 'production_batch',
                referenceId: (string) $batch->id,
                at: $now,
                newSourceType: 'production_batch_void',
                newSourceId: (string) $batch->id
            );

            if ($ledgerRows !== []) {
                StockLedger::query()->insert($ledgerRows);
            }

            $batch->status = ProductionBatch::STATUS_CANCELLED;
            $batch->cancel_reason = 'Void posting: '.$reason;
            $batch->cancelled_by = $userId;
            $batch->cancelled_at = $now;
            $batch->updated_at = $now;
            $batch->save();

            $this->logBatchAudit(
                event: 'production_batch.post_voided',
                tenantId: $tenantId,
                actorId: $userId,
                batchId: (string) $batch->id,
                payload: [
                    'reason' => $reason,
                    'input_count' => $inputs->count(),
                    'output_count' => $outputs->count(),
                    'actual_output_qty' => (float) ($batch->actual_output_qty ?? 0),
                    'transaction_date' => optional($batch->transaction_date)?->format('Y-m-d'),
                ],
                occurredAt: $now
            );

            return [
                'batch_id' => (string) $batch->id,
                'status' => (string) $batch->status,
                'cancel_reason' => $batch->cancel_reason,
                'voided' => true,
            ];
        });
    }

    public function recordPostedWaste(string $batchId, array $payload, ?string $userId): array
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            throw new RuntimeException('Tenant tidak ditemukan.');
        }

        $wasteType = (string) Arr::get($payload, 'waste_type', 'damaged');
        $notes = trim((string) Arr::get($payload, 'notes', ''));
        $items = Arr::get($payload, 'items', []);

        if (! in_array($wasteType, ['damaged', 'expired', 'unsold', 'sample', 'other'], true)) {
            throw ValidationException::withMessages([
                'waste_type' => 'Tipe waste tidak valid.',
            ]);
        }

        if (mb_strlen($notes) < 10) {
            throw ValidationException::withMessages([
                'notes' => 'Catatan waste minimal 10 karakter.',
            ]);
        }

        if (! is_array($items) || $items === []) {
            throw ValidationException::withMessages([
                'items' => 'Minimal satu produk waste wajib diisi.',
            ]);
        }

        $batch = ProductionBatch::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $batchId)
            ->firstOrFail();

        if ($batch->status !== ProductionBatch::STATUS_POSTED) {
            throw new RuntimeException('Waste hanya bisa dicatat untuk batch yang sudah posted.');
        }

        $outputs = ProductionBatchItem::query()
            ->where('tenant_id', $tenantId)
            ->where('production_batch_id', $batchId)
            ->where('line_type', 'output')
            ->get()
            ->keyBy(fn (ProductionBatchItem $item) => $this->itemKey(
                (string) $item->product_id,
                $item->product_variant_id ? (string) $item->product_variant_id : null
            ));

        if ($outputs->isEmpty()) {
            throw new RuntimeException('Output batch tidak ditemukan.');
        }

        $adjustmentItems = [];
        $wasteSummary = [];
        $seenItemKeys = [];
        foreach ($items as $index => $item) {
            $productId = (string) Arr::get($item, 'product_id', '');
            $productVariantId = Arr::get($item, 'product_variant_id');
            $productVariantId = $productVariantId !== null && $productVariantId !== '' ? (string) $productVariantId : null;
            $wasteQty = Decimal::qty(Arr::get($item, 'waste_qty', Arr::get($item, 'qty', 0)));
            $key = $this->itemKey($productId, $productVariantId);
            $output = $outputs->get($key);

            if ($productId === '' || ! $output) {
                throw ValidationException::withMessages([
                    "items.$index.product_id" => 'Produk waste harus berasal dari output batch ini.',
                ]);
            }

            if (isset($seenItemKeys[$key])) {
                throw ValidationException::withMessages([
                    "items.$index.product_id" => 'Produk waste duplikat. Gabungkan qty pada satu baris.',
                ]);
            }
            $seenItemKeys[$key] = true;

            if (Decimal::compare($wasteQty, 0) <= 0) {
                throw ValidationException::withMessages([
                    "items.$index.waste_qty" => 'Qty waste harus lebih dari 0.',
                ]);
            }

            $actualOutputQty = Decimal::qty($output->actual_qty ?? 0);
            $previousWasteQty = $this->postedWasteQtyForOutput(
                $tenantId,
                $batchId,
                (string) $output->product_id,
                $productVariantId
            );
            $totalWasteQty = Decimal::add($previousWasteQty, $wasteQty, Decimal::QTY_SCALE);
            if (Decimal::compare($totalWasteQty, $actualOutputQty) > 0) {
                throw ValidationException::withMessages([
                    "items.$index.waste_qty" => 'Total waste melebihi hasil aktual batch untuk produk ini.',
                ]);
            }

            $currentQty = Decimal::qty($this->inventoryBalanceQuery(
                tenantId: $tenantId,
                locationId: (string) $batch->location_id,
                productId: (string) $output->product_id,
                productVariantId: $productVariantId
            )->value('qty_on_hand') ?? 0);

            if (Decimal::compare($wasteQty, $currentQty) > 0) {
                throw ValidationException::withMessages([
                    "items.$index.waste_qty" => 'Stok produk jadi tidak cukup untuk dicatat waste.',
                ]);
            }

            $physicalQty = Decimal::subtract($currentQty, $wasteQty, Decimal::QTY_SCALE);
            $adjustmentItems[] = [
                'product_id' => (string) $output->product_id,
                'product_variant_id' => $productVariantId,
                'physical_qty' => Decimal::float($physicalQty, Decimal::QTY_SCALE),
            ];
            $wasteSummary[] = [
                'product_id' => (string) $output->product_id,
                'product_variant_id' => $productVariantId,
                'waste_qty' => Decimal::float($wasteQty, Decimal::QTY_SCALE),
                'stock_before' => Decimal::float($currentQty, Decimal::QTY_SCALE),
                'stock_after' => Decimal::float($physicalQty, Decimal::QTY_SCALE),
            ];
        }

        $adjustmentService = app(StockAdjustmentService::class);
        $adjustmentPayload = [
            'location_id' => (string) $batch->location_id,
            'reference_no' => 'WASTE-'.$this->batchReferenceLabel($batch),
            'transaction_date' => Arr::get($payload, 'transaction_date') ?: $batch->transaction_date,
            'reason_code' => 'A02',
            'waste_type' => $wasteType,
            'production_batch_id' => (string) $batch->id,
            'notes' => $notes,
            'items' => $adjustmentItems,
        ];

        $adjustmentId = null;
        try {
            $adjustmentId = $adjustmentService->createDraft($adjustmentPayload, $userId);
            $adjustmentService->approve($adjustmentId, $userId);
            $adjustmentService->post($adjustmentId, $userId, [
                'transaction_date' => Arr::get($adjustmentPayload, 'transaction_date'),
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
            ]);
        } catch (Throwable $exception) {
            if ($adjustmentId) {
                try {
                    $adjustmentService->cancel($adjustmentId, $userId);
                } catch (Throwable) {
                    // Keep the original posting failure visible to the caller.
                }
            }

            throw $exception;
        }

        $this->logBatchAudit(
            event: 'production_batch.waste_posted',
            tenantId: $tenantId,
            actorId: $userId,
            batchId: (string) $batch->id,
            payload: [
                'adjustment_id' => $adjustmentId,
                'waste_type' => $wasteType,
                'notes' => $notes,
                'items' => $wasteSummary,
            ],
            occurredAt: CarbonImmutable::now('UTC')
        );

        return [
            'status' => 'posted',
            'adjustment_id' => $adjustmentId,
            'production_batch_id' => (string) $batch->id,
            'waste_type' => $wasteType,
            'items' => $wasteSummary,
        ];
    }

    private function loadLocation(string $tenantId, string $branchId, string $locationId): Location
    {
        return app(ProductionEligibilityService::class)->loadLocation($tenantId, $branchId, $locationId);
    }

    private function postedWasteQtyForOutput(string $tenantId, string $batchId, string $productId, ?string $productVariantId): string
    {
        $query = DB::table('stock_adjustment_items as sai')
            ->join('stock_adjustments as sa', function ($join) {
                $join->on('sai.stock_adjustment_id', '=', 'sa.id')
                    ->on('sai.tenant_id', '=', 'sa.tenant_id');
            })
            ->where('sa.tenant_id', $tenantId)
            ->where('sa.production_batch_id', $batchId)
            ->where('sa.reason_code', 'A02')
            ->whereIn('sa.status', ['draft', 'approved', 'posted'])
            ->where('sai.product_id', $productId);

        if ($productVariantId) {
            $query->where('sai.product_variant_id', $productVariantId);
        } else {
            $query->whereNull('sai.product_variant_id');
        }

        return Decimal::qty($query
            ->selectRaw('coalesce(sum(case when sai.difference_qty < 0 then abs(sai.difference_qty) else 0 end), 0) as total')
            ->value('total') ?? 0);
    }

    private function batchReferenceLabel(ProductionBatch $batch): string
    {
        $reference = trim((string) ($batch->reference_no ?? ''));

        return $reference !== ''
            ? substr(preg_replace('/[^A-Za-z0-9\-]+/', '-', $reference) ?: $reference, 0, 60)
            : substr((string) $batch->id, 0, 8);
    }

    private function loadBatchProduct(string $tenantId, string $productId): Product
    {
        return app(ProductionEligibilityService::class)->loadBatchProduct($tenantId, $productId);
    }

    private function normalizeRecipeLines(Product $product): array
    {
        return app(ProductionEligibilityService::class)->normalizeRecipeLines($product);
    }

    private function loadProductsForRecipe(string $tenantId, array $productIds): Collection
    {
        $products = Product::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereIn('id', $productIds)
            ->get(['id', 'item_type', 'product_mode'])
            ->keyBy('id');

        if ($products->count() !== count(array_unique($productIds))) {
            throw ValidationException::withMessages([
                'product_id' => 'Komponen recipe tidak ditemukan untuk tenant aktif.',
            ]);
        }

        return $products;
    }

    private function resolveVariantId(string $tenantId, string $productId, ?string $variantId): ?string
    {
        if ($variantId) {
            $exists = DB::table('product_variants')
                ->where('tenant_id', $tenantId)
                ->where('id', $variantId)
                ->where('product_id', $productId)
                ->exists();

            if (! $exists) {
                throw new RuntimeException('Varian produk batch produksi tidak valid.');
            }

            return $variantId;
        }

        $hasVariants = DB::table('product_variants')
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->exists();

        if ($hasVariants) {
            throw new RuntimeException('Produk varian memerlukan product_variant_id pada production batch.');
        }

        return null;
    }

    private function productHasVariants(string $tenantId, string $productId): bool
    {
        return DB::table('product_variants')
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->exists();
    }

    private function assertOutputProductReady(string $tenantId, string $productId, int $index): void
    {
        $product = Product::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $productId)
            ->first();

        if (! $product || ! $product->is_active) {
            throw ValidationException::withMessages([
                "outputs.$index.product_id" => 'Produk hasil tidak ditemukan atau tidak aktif.',
            ]);
        }

        $itemType = ProductItemTypeMapper::resolve($product->item_type, (string) ($product->product_mode ?? 'default'));

        if ($itemType !== ProductItemTypeMapper::ITEM_FINISHED_GOOD || ! $product->track_stock) {
            throw ValidationException::withMessages([
                "outputs.$index.product_id" => 'Produk hasil harus finished_good dan track_stock aktif.',
            ]);
        }
    }

    private function resolveDraftPlan(array $payload, Product $product, ?ProductionBatch $batch = null): array
    {
        $hasExpectedOutputQty = array_key_exists('expected_output_qty', $payload);
        $useRecipeCountMode = array_key_exists('recipe_count', $payload)
            || (! $hasExpectedOutputQty && array_key_exists('recipe_version', $payload));
        $recipeOutputQty = Decimal::snappedQty($product->recipe_output_qty ?? 0);

        if ($useRecipeCountMode) {
            $recipeCount = (int) Arr::get($payload, 'recipe_count', Arr::get($payload, 'recipe_version'));

            if ($recipeCount < 1) {
                throw ValidationException::withMessages([
                    'recipe_count' => 'Jumlah resep wajib lebih dari 0.',
                ]);
            }

            if (Decimal::compare($recipeOutputQty, '0') <= 0) {
                throw ValidationException::withMessages([
                    'recipe_output_qty' => 'Produk batch production wajib punya hasil standar per 1 resep.',
                ]);
            }

            return [
                'mode' => 'recipe_count',
                'recipe_count' => $recipeCount,
                'recipe_output_qty' => $recipeOutputQty,
                'expected_output_qty' => Decimal::multiply(
                    Decimal::qty((string) $recipeCount),
                    $recipeOutputQty,
                    Decimal::QTY_SCALE
                ),
                'input_multiplier' => Decimal::qty((string) $recipeCount),
            ];
        }

        $expectedOutputQty = $hasExpectedOutputQty
            ? Decimal::qty($payload['expected_output_qty'])
            : Decimal::qty($batch?->expected_output_qty ?? 0);

        if (Decimal::compare($expectedOutputQty, '0') <= 0) {
            throw ValidationException::withMessages([
                'expected_output_qty' => 'Expected output qty harus lebih dari 0.',
            ]);
        }

        return [
            'mode' => 'legacy_output',
            'recipe_count' => (int) Arr::get($payload, 'recipe_version', $batch?->recipe_version ?? 1),
            'recipe_output_qty' => $recipeOutputQty,
            'expected_output_qty' => $expectedOutputQty,
            'input_multiplier' => $expectedOutputQty,
        ];
    }

    private function buildPlannedInputsFromRecipeLines(array $recipeLines, string $multiplier): array
    {
        $plannedInputs = [];

        foreach ($recipeLines as $line) {
            $plannedInputs[$this->itemKey(
                (string) $line['product_id'],
                Arr::get($line, 'product_variant_id')
            )] = Decimal::float(
                Decimal::multiply($multiplier, Decimal::qty($line['qty']), Decimal::QTY_SCALE),
                Decimal::QTY_SCALE
            );
        }

        return $plannedInputs;
    }

    private function normalizeDraftOutputs(string $tenantId, array $payload, Product $defaultProduct, ?string $defaultVariantId, array $draftPlan): array
    {
        $outputs = Arr::get($payload, 'outputs');

        if (! is_array($outputs) || $outputs === []) {
            return [[
                'product_id' => (string) $defaultProduct->id,
                'product_variant_id' => $defaultVariantId,
                'planned_qty' => Decimal::qty($draftPlan['expected_output_qty']),
                'recipe_factor' => null,
            ]];
        }

        $normalized = [];
        $recipeUsage = Decimal::qty('0');
        $recipeCount = Decimal::qty($draftPlan['recipe_count'] ?? 0);

        foreach ($outputs as $index => $output) {
            if (! is_array($output)) {
                throw ValidationException::withMessages([
                    "outputs.$index" => 'Output harus berupa data produk jadi.',
                ]);
            }

            $productId = (string) ($output['product_id'] ?? '');
            if ($productId === '') {
                throw ValidationException::withMessages([
                    "outputs.$index.product_id" => 'Produk hasil wajib diisi.',
                ]);
            }

            $this->assertOutputProductReady($tenantId, $productId, $index);
            $productVariantId = isset($output['product_variant_id']) && $output['product_variant_id'] !== ''
                ? (string) $output['product_variant_id']
                : null;
            $productVariantId = $this->resolveVariantId($tenantId, $productId, $productVariantId);

            $plannedQty = Decimal::qty($output['planned_qty'] ?? $output['qty'] ?? 0);
            $recipeFactor = Decimal::recipeFactor($output['recipe_factor'] ?? 0);

            if (Decimal::compare($plannedQty, '0') <= 0) {
                throw ValidationException::withMessages([
                    "outputs.$index.planned_qty" => 'Qty hasil produksi harus lebih dari 0.',
                ]);
            }

            if (Decimal::compare($recipeFactor, '0') <= 0) {
                throw ValidationException::withMessages([
                    "outputs.$index.recipe_factor" => 'Faktor resep wajib lebih dari 0 untuk hasil multi-output.',
                ]);
            }

            $key = $this->itemKey($productId, $productVariantId);
            if (isset($normalized[$key])) {
                throw ValidationException::withMessages([
                    "outputs.$index.product_id" => 'Produk hasil duplikat. Gabungkan qty pada satu baris.',
                ]);
            }

            $recipeUsage = Decimal::add(
                $recipeUsage,
                Decimal::multiply($plannedQty, $recipeFactor, Decimal::QTY_SCALE),
                Decimal::QTY_SCALE
            );

            $normalized[$key] = [
                'product_id' => $productId,
                'product_variant_id' => $productVariantId,
                'planned_qty' => $plannedQty,
                'recipe_factor' => $recipeFactor,
            ];
        }

        if (Decimal::compare($recipeUsage, $recipeCount) > 0) {
            throw ValidationException::withMessages([
                'outputs' => sprintf(
                    'Total hasil memakai %s resep, melebihi jumlah resep dibuat %s.',
                    Decimal::float($recipeUsage, Decimal::QTY_SCALE),
                    Decimal::float($recipeCount, Decimal::QTY_SCALE)
                ),
            ]);
        }

        return array_values($normalized);
    }

    private function normalizeActualOutputs(array $payload, Collection $outputs): array
    {
        $postedOutputs = Arr::get($payload, 'outputs');

        if (! is_array($postedOutputs) || $postedOutputs === []) {
            $output = $outputs->first();

            return [
                $this->itemKey((string) $output->product_id, $output->product_variant_id ? (string) $output->product_variant_id : null)
                    => Decimal::float(Decimal::qty($payload['actual_output_qty'] ?? 0), Decimal::QTY_SCALE),
            ];
        }

        $normalized = [];

        foreach ($postedOutputs as $index => $output) {
            $productId = (string) ($output['product_id'] ?? '');
            $productVariantId = isset($output['product_variant_id']) && $output['product_variant_id'] !== ''
                ? (string) $output['product_variant_id']
                : null;
            $qty = Decimal::qty($output['actual_qty'] ?? $output['qty'] ?? 0);

            if ($productId === '') {
                throw ValidationException::withMessages([
                    "outputs.$index.product_id" => 'Produk hasil wajib diisi.',
                ]);
            }

            if (Decimal::compare($qty, '0') < 0) {
                throw ValidationException::withMessages([
                    "outputs.$index.actual_qty" => 'Qty hasil aktual tidak boleh negatif.',
                ]);
            }

            $key = $this->itemKey($productId, $productVariantId);
            if (array_key_exists($key, $normalized)) {
                throw ValidationException::withMessages([
                    "outputs.$index.product_id" => 'Produk hasil aktual duplikat.',
                ]);
            }

            $normalized[$key] = Decimal::float($qty, Decimal::QTY_SCALE);
        }

        return $normalized;
    }

    private function outputAllocationBase(Collection $outputs, array $actualOutputMap): string
    {
        $total = Decimal::qty('0');

        foreach ($outputs as $output) {
            $key = $this->itemKey((string) $output->product_id, $output->product_variant_id ? (string) $output->product_variant_id : null);
            $qty = Decimal::qty($actualOutputMap[$key] ?? 0);
            $factor = Decimal::recipeFactor($output->recipe_factor ?? 0);
            $allocationUnits = Decimal::compare($factor, '0') > 0
                ? Decimal::multiply($qty, $factor, Decimal::QTY_SCALE)
                : $qty;
            $total = Decimal::add($total, $allocationUnits, Decimal::QTY_SCALE);
        }

        return $total;
    }

    private function normalizeActualInputs(array $inputs): array
    {
        $normalized = [];

        foreach ($inputs as $index => $input) {
            $productId = (string) ($input['product_id'] ?? '');
            $productVariantId = isset($input['product_variant_id']) && $input['product_variant_id'] !== ''
                ? (string) $input['product_variant_id']
                : null;

            if ($productId === '') {
                throw ValidationException::withMessages([
                    "inputs.$index.product_id" => 'Product input wajib diisi.',
                ]);
            }

            $qty = Decimal::qty($input['actual_qty'] ?? 0);
            if (Decimal::compare($qty, '0') < 0) {
                throw ValidationException::withMessages([
                    "inputs.$index.actual_qty" => 'Qty input aktual tidak boleh negatif.',
                ]);
            }

            $key = $this->itemKey($productId, $productVariantId);
            if (array_key_exists($key, $normalized)) {
                throw ValidationException::withMessages([
                    "inputs.$index.product_id" => 'Input aktual duplikat terdeteksi.',
                ]);
            }

            $normalized[$key] = Decimal::float($qty, Decimal::QTY_SCALE);
        }

        return $normalized;
    }

    private function normalizePlannedInputs(array $inputs): array
    {
        $normalized = [];

        foreach ($inputs as $index => $input) {
            $productId = (string) ($input['product_id'] ?? '');
            $productVariantId = isset($input['product_variant_id']) && $input['product_variant_id'] !== ''
                ? (string) $input['product_variant_id']
                : null;

            if ($productId === '') {
                throw ValidationException::withMessages([
                    "inputs.$index.product_id" => 'Product input wajib diisi.',
                ]);
            }

            $qty = Decimal::qty($input['planned_qty'] ?? 0);
            if (Decimal::compare($qty, '0') <= 0) {
                throw ValidationException::withMessages([
                    "inputs.$index.planned_qty" => 'Planned qty input harus lebih dari 0.',
                ]);
            }

            $key = $this->itemKey($productId, $productVariantId);
            if (array_key_exists($key, $normalized)) {
                throw ValidationException::withMessages([
                    "inputs.$index.product_id" => 'Input draft duplikat terdeteksi.',
                ]);
            }

            $normalized[$key] = Decimal::float($qty, Decimal::QTY_SCALE);
        }

        return $normalized;
    }

    private function validateDraftInputKeys(Collection $inputs, array $plannedInputMap, string $field): void
    {
        $expectedKeys = $inputs
            ->map(fn (ProductionBatchItem $item) => $this->itemKey((string) $item->product_id, $item->product_variant_id ? (string) $item->product_variant_id : null))
            ->values()
            ->all();
        $actualKeys = array_keys($plannedInputMap);

        sort($expectedKeys);
        sort($actualKeys);

        if ($expectedKeys !== $actualKeys) {
            throw ValidationException::withMessages([
                $field => 'Input draft harus persis sama dengan snapshot batch.',
            ]);
        }
    }

    private function assertDraftMutable(ProductionBatch $batch): void
    {
        if ($batch->status !== ProductionBatch::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'batch' => 'Batch draft sudah tidak bisa diubah atau dibatalkan.',
            ]);
        }
    }

    private function normalizeCancelReason(mixed $value): string
    {
        $reason = trim((string) $value);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'cancel_reason' => 'Cancel reason wajib diisi saat membatalkan draft batch.',
            ]);
        }

        if (mb_strlen($reason) > 2000) {
            throw ValidationException::withMessages([
                'cancel_reason' => 'Cancel reason maksimal 2000 karakter.',
            ]);
        }

        return $reason;
    }

    private function validatePostPayload(
        ProductionBatch $batch,
        Collection $inputs,
        Collection $outputs,
        array $actualInputMap,
        array $actualOutputMap,
        string $actualOutputQty,
        ?string $varianceReason = null
    ): void {
        if ($batch->product_id === null || $batch->branch_id === null || $batch->location_id === null) {
            throw ValidationException::withMessages([
                'batch' => 'Batch tidak valid untuk dipost.',
            ]);
        }

        if (Decimal::compare($actualOutputQty, '0') <= 0) {
            throw ValidationException::withMessages([
                'actual_output_qty' => 'Actual output qty harus lebih dari 0.',
            ]);
        }

        if ($inputs->isEmpty()) {
            throw ValidationException::withMessages([
                'inputs' => 'Batch harus memiliki minimal satu input.',
            ]);
        }

        $varianceState = $this->yieldVarianceState(
            (float) ($batch->expected_output_qty ?? 0),
            Decimal::float($actualOutputQty, Decimal::QTY_SCALE)
        );

        if ($this->varianceReasonIndicatesInputMistake($varianceReason)) {
            throw ValidationException::withMessages([
                'variance_reason' => 'Alasan menunjukkan salah input. Jangan posting batch; edit draft atau batalkan draft dulu agar stok dan HPP tidak bergerak dari angka salah.',
            ]);
        }

        if ($varianceState['blocks_posting']) {
            throw ValidationException::withMessages([
                'actual_output_qty' => 'Selisih hasil terlalu besar dan kemungkinan salah input. Koreksi draft atau batalkan batch dulu.',
            ]);
        }

        $expectedKeys = $inputs
            ->map(fn (ProductionBatchItem $item) => $this->itemKey((string) $item->product_id, $item->product_variant_id ? (string) $item->product_variant_id : null))
            ->values()
            ->all();
        $actualKeys = array_keys($actualInputMap);

        sort($expectedKeys);
        sort($actualKeys);

        if ($expectedKeys !== $actualKeys) {
            throw ValidationException::withMessages([
                'inputs' => 'Input aktual harus persis sama dengan draft batch.',
            ]);
        }

        $expectedOutputKeys = $outputs
            ->map(fn (ProductionBatchItem $item) => $this->itemKey((string) $item->product_id, $item->product_variant_id ? (string) $item->product_variant_id : null))
            ->values()
            ->all();
        $actualOutputKeys = array_keys($actualOutputMap);

        if ($outputs->count() > 1 || collect($outputs)->contains(fn (ProductionBatchItem $item) => $item->recipe_factor !== null)) {
            sort($expectedOutputKeys);
            sort($actualOutputKeys);

            if ($expectedOutputKeys !== $actualOutputKeys) {
                throw ValidationException::withMessages([
                    'outputs' => 'Output aktual harus persis sama dengan draft batch.',
                ]);
            }
        }

        $hasRecipeFactors = $outputs->contains(fn (ProductionBatchItem $item) => $item->recipe_factor !== null && (float) $item->recipe_factor > 0);

        if (! $hasRecipeFactors) {
            if ($varianceState['requires_reason']
                && trim((string) $varianceReason) === '') {
                throw ValidationException::withMessages([
                    'variance_reason' => 'Variance reason wajib diisi saat yield variance menyentuh threshold monitoring.',
                ]);
            }

            return;
        }

        $actualRecipeUsage = $this->outputAllocationBase($outputs, $actualOutputMap);

        if (Decimal::compare($actualRecipeUsage, Decimal::qty($batch->recipe_version ?? 0)) > 0) {
            throw ValidationException::withMessages([
                'outputs' => sprintf(
                    'Total output aktual memakai %s resep, melebihi jumlah resep dibuat %s.',
                    Decimal::float($actualRecipeUsage, Decimal::QTY_SCALE),
                    Decimal::float($batch->recipe_version ?? 0, Decimal::QTY_SCALE)
                ),
            ]);
        }

        if ($varianceState['requires_reason']
            && trim((string) $varianceReason) === '') {
            throw ValidationException::withMessages([
                'variance_reason' => 'Variance reason wajib diisi saat yield variance menyentuh threshold monitoring.',
            ]);
        }
    }

    private function lockInventoryRows(string $tenantId, string $locationId, Collection $inputs, Collection $outputs): void
    {
        $lines = $inputs->concat($outputs)
            ->map(fn (ProductionBatchItem $item) => [
                'product_id' => (string) $item->product_id,
                'product_variant_id' => $item->product_variant_id ? (string) $item->product_variant_id : null,
            ])
            ->unique(fn (array $line) => $this->itemKey($line['product_id'], $line['product_variant_id']))
            ->values();

        foreach ($lines as $line) {
            $this->inventoryBalanceQuery(
                tenantId: $tenantId,
                productId: $line['product_id'],
                productVariantId: $line['product_variant_id'],
                locationId: $locationId,
            )->lockForUpdate()->get();

            DB::table('inventory_cost_states')
                ->where('tenant_id', $tenantId)
                ->where('product_id', $line['product_id'])
                ->where('location_id', $locationId)
                ->when($line['product_variant_id'], function ($query, $variantId) {
                    $query->where('product_variant_id', $variantId);
                }, function ($query) {
                    $query->whereNull('product_variant_id');
                })
                ->lockForUpdate()
                ->get();
        }
    }

    private function inventoryBalanceQuery(string $tenantId, string $productId, ?string $productVariantId, string $locationId)
    {
        return InventoryBalance::query()
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where('location_id', $locationId)
            ->when($productVariantId, function ($query) use ($productVariantId) {
                $query->where('product_variant_id', $productVariantId);
            }, function ($query) {
                $query->whereNull('product_variant_id');
            });
    }

    private function itemKey(string $productId, ?string $productVariantId): string
    {
        return $productId.'|'.($productVariantId ?? 'null');
    }

    private function logBatchAudit(
        string $event,
        string $tenantId,
        ?string $actorId,
        string $batchId,
        array $payload,
        ?CarbonImmutable $occurredAt = null
    ): void {
        app(AuditLogger::class)->log(
            event: $event,
            actorId: $actorId,
            referenceType: 'production_batch',
            referenceId: $batchId,
            payload: $payload,
            ipAddress: request()?->ip(),
            userAgent: request()?->userAgent(),
            tenantId: $tenantId,
            occurredAt: $occurredAt
        );
    }

    private function draftAuditSnapshot(ProductionBatch $batch, Collection $inputs, Collection $outputs): array
    {
        return [
            'transaction_date' => optional($batch->transaction_date)?->format('Y-m-d'),
            'expected_output_qty' => (float) ($batch->expected_output_qty ?? 0),
            'recipe_version' => (int) ($batch->recipe_version ?? 0),
            'recipe_count' => (int) ($batch->recipe_version ?? 0),
            'reference_no' => $batch->reference_no,
            'notes' => $batch->notes,
            'variance_reason' => $batch->variance_reason,
            'cancel_reason' => $batch->cancel_reason,
            'product_variant_id' => $batch->product_variant_id,
            'input_count' => $inputs->count(),
            'input_planned_qty_total' => (float) $inputs->sum(fn (ProductionBatchItem $item) => (float) ($item->planned_qty ?? 0)),
            'inputs' => $inputs->map(fn (ProductionBatchItem $item) => [
                'product_id' => (string) $item->product_id,
                'product_variant_id' => $item->product_variant_id,
                'planned_qty' => (float) ($item->planned_qty ?? 0),
            ])->values()->all(),
            'outputs' => $outputs->map(fn (ProductionBatchItem $output) => [
                'product_id' => (string) $output->product_id,
                'product_variant_id' => $output->product_variant_id,
                'planned_qty' => (float) ($output->planned_qty ?? 0),
                'recipe_factor' => $output->recipe_factor !== null ? (float) $output->recipe_factor : null,
            ])->values()->all(),
        ];
    }

    private function postAuditSnapshot(ProductionBatch $batch, Collection $inputs, Collection $outputs, string $idempotencyKey): array
    {
        $totalInputCost = $inputs->sum(function (ProductionBatchItem $item) {
            return (float) ($item->actual_qty ?? 0) * (float) ($item->unit_cost_snapshot ?? 0);
        });

        return [
            'status' => (string) $batch->status,
            'transaction_date' => optional($batch->transaction_date)?->format('Y-m-d'),
            'expected_output_qty' => (float) ($batch->expected_output_qty ?? 0),
            'recipe_version' => (int) ($batch->recipe_version ?? 0),
            'recipe_count' => (int) ($batch->recipe_version ?? 0),
            'actual_output_qty' => (float) ($batch->actual_output_qty ?? 0),
            'yield_variance_qty' => (float) ($batch->yield_variance_qty ?? 0),
            'yield_variance_pct' => (float) ($batch->yield_variance_pct ?? 0),
            'reference_no' => $batch->reference_no,
            'notes' => $batch->notes,
            'variance_reason' => $batch->variance_reason,
            'cancel_reason' => $batch->cancel_reason,
            'idempotency_key' => $idempotencyKey,
            'input_count' => $inputs->count(),
            'input_actual_qty_total' => (float) $inputs->sum(fn (ProductionBatchItem $item) => (float) ($item->actual_qty ?? 0)),
            'total_input_cost' => $totalInputCost,
            'outputs' => $outputs->map(fn (ProductionBatchItem $output) => [
                'product_id' => (string) $output->product_id,
                'product_variant_id' => $output->product_variant_id,
                'actual_qty' => (float) ($output->actual_qty ?? 0),
                'recipe_factor' => $output->recipe_factor !== null ? (float) $output->recipe_factor : null,
                'unit_cost_snapshot' => (float) ($output->unit_cost_snapshot ?? 0),
            ])->values()->all(),
        ];
    }

    private function raiseYieldVarianceAnomalyIfNeeded(
        ProductionBatch $batch,
        string $tenantId,
        Collection $inputs,
        Collection $outputs
    ): void {
        [$pctThreshold, $qtyThreshold] = $this->yieldVarianceThresholds();

        if (! $this->yieldVarianceExceedsAlertThreshold(
            (float) ($batch->expected_output_qty ?? 0),
            (float) ($batch->actual_output_qty ?? 0)
        )) {
            return;
        }

        $severity = strtolower((string) config('services.anomaly.production_batch_yield_variance_severity', 'high'));
        if (! in_array($severity, ['low', 'med', 'high', 'critical'], true)) {
            $severity = 'high';
        }

        app(AnomalyService::class)->raise(
            tenantId: $tenantId,
            severity: $severity,
            type: 'production_batch_yield_variance',
            payload: [
                'source' => 'production_batch_post',
                'batch_id' => (string) $batch->id,
                'branch_id' => (string) $batch->branch_id,
                'location_id' => (string) $batch->location_id,
                'product_id' => (string) $batch->product_id,
                'product_variant_id' => $batch->product_variant_id ? (string) $batch->product_variant_id : null,
                'reference_no' => $batch->reference_no,
                'transaction_date' => optional($batch->transaction_date)?->format('Y-m-d'),
                'expected_output_qty' => (float) ($batch->expected_output_qty ?? 0),
                'actual_output_qty' => (float) ($batch->actual_output_qty ?? 0),
                'yield_variance_qty' => (float) ($batch->yield_variance_qty ?? 0),
                'yield_variance_pct' => (float) ($batch->yield_variance_pct ?? 0),
                'variance_reason' => $batch->variance_reason,
                'input_count' => $inputs->count(),
                'input_actual_qty_total' => (float) $inputs->sum(fn (ProductionBatchItem $item) => (float) ($item->actual_qty ?? 0)),
                'output_count' => $outputs->count(),
                'output_unit_cost' => (float) ($outputs->first()?->unit_cost_snapshot ?? 0),
                'threshold_pct' => $pctThreshold,
                'threshold_qty' => $qtyThreshold,
            ],
            referenceId: (string) $batch->id
        );
    }

    private function yieldVarianceThresholds(): array
    {
        return [
            max(0.0, (float) config('services.anomaly.production_batch_yield_variance_pct_threshold', 10)),
            max(0.0, (float) config('services.anomaly.production_batch_yield_variance_qty_threshold', 1)),
        ];
    }

    private function yieldVarianceExceedsAlertThreshold(float $expectedOutputQty, float $actualOutputQty): bool
    {
        return $this->yieldVarianceState($expectedOutputQty, $actualOutputQty)['requires_reason'];
    }

    private function yieldVarianceState(float $expectedOutputQty, float $actualOutputQty): array
    {
        [$pctThreshold, $qtyThreshold] = $this->yieldVarianceThresholds();
        $varianceQty = abs($actualOutputQty - $expectedOutputQty);
        $variancePct = $expectedOutputQty > 0
            ? abs((($actualOutputQty - $expectedOutputQty) * 100) / $expectedOutputQty)
            : 0.0;

        return [
            'variance_qty' => $varianceQty,
            'variance_pct' => $variancePct,
            'requires_reason' => $varianceQty >= $qtyThreshold || $variancePct >= $pctThreshold,
            'requires_confirmation' => $variancePct >= 20.0,
            'blocks_posting' => $variancePct >= 50.0,
        ];
    }

    private function varianceReasonIndicatesInputMistake(?string $varianceReason): bool
    {
        $reason = mb_strtolower(trim((string) $varianceReason));

        return str_contains($reason, 'salah input')
            || str_contains($reason, 'typo')
            || str_contains($reason, 'koreksi');
    }

    private function idempotencyCacheKey(string $tenantId, string $batchId, string $idempotencyKey): string
    {
        return 'production_batch:post:'.sha1($tenantId.'|'.$batchId.'|'.$idempotencyKey);
    }

    private function serializePostResult(ProductionBatch $batch, bool $idempotent): array
    {
        return [
            'batch_id' => (string) $batch->id,
            'status' => (string) $batch->status,
            'posted_at' => optional($batch->posted_at)->toISOString(),
            'actual_output_qty' => (float) ($batch->actual_output_qty ?? 0),
            'yield_variance_qty' => (float) ($batch->yield_variance_qty ?? 0),
            'yield_variance_pct' => (float) ($batch->yield_variance_pct ?? 0),
            'idempotent' => $idempotent,
        ];
    }

    private function serializeDraftResult(ProductionBatch $batch, Collection $inputs, Collection $outputs): array
    {
        $firstOutput = $outputs->first();

        return [
            'batch_id' => (string) $batch->id,
            'status' => (string) $batch->status,
            'transaction_date' => optional($batch->transaction_date)?->format('Y-m-d'),
            'expected_output_qty' => (float) ($batch->expected_output_qty ?? 0),
            'recipe_version' => (int) ($batch->recipe_version ?? 0),
            'recipe_count' => (int) ($batch->recipe_version ?? 0),
            'reference_no' => $batch->reference_no,
            'notes' => $batch->notes,
            'product_variant_id' => $batch->product_variant_id,
            'inputs' => $inputs->map(fn (ProductionBatchItem $item) => [
                'id' => (string) $item->id,
                'product_id' => (string) $item->product_id,
                'product_variant_id' => $item->product_variant_id,
                'planned_qty' => (float) ($item->planned_qty ?? 0),
                'uom' => $item->uom,
            ])->values()->all(),
            'output' => $firstOutput ? [
                'id' => (string) $firstOutput->id,
                'product_id' => (string) $firstOutput->product_id,
                'product_variant_id' => $firstOutput->product_variant_id,
                'planned_qty' => (float) ($firstOutput->planned_qty ?? 0),
                'recipe_factor' => $firstOutput->recipe_factor !== null ? (float) $firstOutput->recipe_factor : null,
            ] : null,
            'outputs' => $outputs->map(fn (ProductionBatchItem $output) => [
                'id' => (string) $output->id,
                'product_id' => (string) $output->product_id,
                'product_variant_id' => $output->product_variant_id,
                'planned_qty' => (float) ($output->planned_qty ?? 0),
                'recipe_factor' => $output->recipe_factor !== null ? (float) $output->recipe_factor : null,
            ])->values()->all(),
        ];
    }

    private function decrementInventory(string $tenantId, string $productId, ?string $productVariantId, string $locationId, float $qty): int
    {
        if ($productVariantId) {
            return DB::update(
                'UPDATE inventory_balances SET qty_on_hand = qty_on_hand - ? WHERE tenant_id = ? AND product_id = ? AND product_variant_id = ? AND location_id = ? AND qty_on_hand >= ?',
                [$qty, $tenantId, $productId, $productVariantId, $locationId, $qty]
            );
        }

        return DB::update(
            'UPDATE inventory_balances SET qty_on_hand = qty_on_hand - ? WHERE tenant_id = ? AND product_id = ? AND location_id = ? AND product_variant_id IS NULL AND qty_on_hand >= ?',
            [$qty, $tenantId, $productId, $locationId, $qty]
        );
    }

    private function ensureInventoryBalanceBucket(string $tenantId, string $productId, ?string $productVariantId, string $locationId): void
    {
        InventoryBalance::query()->firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'product_id' => $productId,
                'product_variant_id' => $productVariantId,
                'location_id' => $locationId,
            ],
            [
                'qty_on_hand' => 0,
            ]
        );
    }

    private function incrementInventory(string $tenantId, string $productId, ?string $productVariantId, string $locationId, float $qty): void
    {
        if ($productVariantId) {
            DB::update(
                'UPDATE inventory_balances SET qty_on_hand = qty_on_hand + ? WHERE tenant_id = ? AND product_id = ? AND product_variant_id = ? AND location_id = ?',
                [$qty, $tenantId, $productId, $productVariantId, $locationId]
            );

            return;
        }

        DB::update(
            'UPDATE inventory_balances SET qty_on_hand = qty_on_hand + ? WHERE tenant_id = ? AND product_id = ? AND location_id = ? AND product_variant_id IS NULL',
            [$qty, $tenantId, $productId, $locationId]
        );
    }
}
