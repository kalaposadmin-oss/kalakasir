<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccountMapping;
use App\Models\InventoryBalance;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\StockLedger;
use App\Exceptions\LockPeriodException;
use App\Services\LockPeriodService;
use App\Services\InventoryValuationService;
use App\Events\PurchaseReturned;
use App\Services\AnomalyService;
use App\Services\TransactionDateService;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class PurchaseReturnController extends Controller
{
    public function store(Request $request)
    {
        $tenantId = TenantContext::id();

        $validated = $request->validate([
            'purchase_id' => ['required', 'uuid'],
            'supplier_id' => ['required', 'uuid'],
            'transaction_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'uuid'],
            'items.*.qty' => ['required', 'numeric', 'min:0.01'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'items.*.reason' => ['nullable', 'string', 'max:100'],
        ]);

        $purchase = Purchase::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $validated['purchase_id'])
            ->firstOrFail();
        if (! $purchase->location_id) {
            return response()->json([
                'code' => 'LOCATION_NOT_FOUND',
                'message' => 'Lokasi purchase belum ditentukan.',
                'details' => [],
            ], 422);
        }

        if ((string) $purchase->supplier_id !== (string) $validated['supplier_id']) {
            return response()->json([
                'code' => 'PURCHASE_SUPPLIER_MISMATCH',
                'message' => 'Supplier retur harus sama dengan supplier purchase asal.',
                'details' => [],
            ], 422);
        }

        $total = collect($validated['items'])->sum(function ($item) {
            return (float) $item['qty'] * (float) $item['price'];
        });

        $now = CarbonImmutable::now('UTC');
        $transactionDate = (new TransactionDateService())
            ->resolve($validated['transaction_date'] ?? null);
        $lockService = new LockPeriodService();

        try {
            $lockService->ensureNotLocked($transactionDate, $tenantId);
        } catch (LockPeriodException $exception) {
            return response()->json([
                'code' => $exception->errorCode(),
                'message' => $exception->getMessage(),
                'details' => [],
            ], $exception->status());
        } catch (InvalidArgumentException $exception) {
            app(AnomalyService::class)->raise(
                tenantId: $tenantId,
                severity: 'low',
                type: 'invalid_transaction_date_attempt',
                payload: [
                    'source' => 'purchase_return',
                    'message' => $exception->getMessage(),
                ]
            );

            return response()->json([
                'code' => 'INVALID_TRANSACTION_DATE',
                'message' => $exception->getMessage(),
                'details' => [],
            ], 422);
        }

        try {
            $result = app(\App\Services\DatabaseTransactionService::class)->run(function () use ($tenantId, $validated, $purchase, $total, $now, $request, $transactionDate) {
                $valuationService = app(InventoryValuationService::class);

                $returnId = (string) Str::uuid();
                PurchaseReturn::query()->create([
                    'id' => $returnId,
                    'tenant_id' => $tenantId,
                    'purchase_id' => $validated['purchase_id'],
                    'supplier_id' => $purchase->supplier_id,
                    'total' => $total,
                    'status' => 'received',
                    'notes' => $validated['notes'] ?? null,
                    'created_at' => $now,
                    'transaction_date' => $transactionDate->toDateString(),
                ]);

                $items = collect($validated['items'])->map(function ($item) use ($tenantId, $returnId) {
                    return [
                        'id' => (string) Str::uuid(),
                        'tenant_id' => $tenantId,
                        'purchase_return_id' => $returnId,
                        'product_id' => $item['product_id'],
                        'qty' => (float) $item['qty'],
                        'price' => (float) $item['price'],
                        'reason' => $item['reason'] ?? null,
                    ];
                })->all();

                PurchaseReturnItem::query()->insert($items);

                $ledgerRows = [];
                $totalValuationCost = 0.0;

                foreach ($items as $item) {
                    $qty = (float) $item['qty'];

                    $affected = DB::update(
                        'UPDATE inventory_balances SET qty_on_hand = qty_on_hand - ? WHERE tenant_id = ? AND product_id = ? AND location_id = ? AND qty_on_hand >= ?',
                        [$qty, $tenantId, $item['product_id'], $purchase->location_id, $qty]
                    );

                    if ($affected === 0) {
                        app(AnomalyService::class)->raise(
                            tenantId: $tenantId,
                            severity: 'high',
                            type: 'negative_stock_attempt',
                            payload: [
                                'source' => 'purchase_return',
                                'purchase_return_id' => $returnId,
                                'product_id' => $item['product_id'],
                                'location_id' => $purchase->location_id,
                                'requested_qty' => $qty,
                            ],
                            referenceId: $returnId
                        );
                        throw new RuntimeException('Stok tidak mencukupi untuk retur.');
                    }

                    $balanceAfter = (float) InventoryBalance::query()
                        ->where('tenant_id', $tenantId)
                        ->where('product_id', $item['product_id'])
                        ->where('location_id', $purchase->location_id)
                        ->value('qty_on_hand');

                    $ledgerRows[] = [
                        'id' => (string) Str::uuid(),
                        'tenant_id' => $tenantId,
                        'product_id' => $item['product_id'],
                        'location_id' => $purchase->location_id,
                        'reference_type' => 'purchase_return',
                        'reference_id' => $returnId,
                        'reference_item_id' => $item['id'],
                        'qty_in' => 0,
                        'qty_out' => $qty,
                        'balance_after' => $balanceAfter,
                        'created_at' => $now,
                        'created_by' => $request->user()?->id,
                    ];

                    $valuationResult = $valuationService->issue(
                        tenantId: $tenantId,
                        productId: $item['product_id'],
                        locationId: $purchase->location_id,
                        qty: $qty,
                        referenceType: 'purchase_return',
                        referenceId: $returnId,
                        referenceItemId: $item['id'],
                        at: $now
                    );

                    $totalValuationCost += (float) ($valuationResult['total_cost'] ?? 0);
                }

                StockLedger::query()->insert($ledgerRows);

                $mapping = AccountMapping::query()
                    ->where('tenant_id', $tenantId)
                    ->firstOrFail();

                $journalEntryId = (string) Str::uuid();
                JournalEntry::query()->create([
                    'id' => $journalEntryId,
                    'tenant_id' => $tenantId,
                    'reference_type' => 'purchase_return',
                    'reference_id' => $returnId,
                    'date' => $transactionDate->toDateString(),
                    'description' => 'Purchase return',
                    'status' => 'posted',
                    'posted_at' => $now,
                    'created_by' => $request->user()?->id,
                    'created_at' => $now,
                ]);

                $documentTotal = round($total, 2);
                $inventoryTotal = round($totalValuationCost, 2);
                $variance = round($documentTotal - $inventoryTotal, 2);

                $journalItems = [
                    [
                        'id' => (string) Str::uuid(),
                        'journal_entry_id' => $journalEntryId,
                        'tenant_id' => $tenantId,
                        'account_id' => $mapping->payable_account_id,
                        'debit' => $documentTotal,
                        'credit' => 0,
                        'is_reversal' => false,
                    ],
                    [
                        'id' => (string) Str::uuid(),
                        'journal_entry_id' => $journalEntryId,
                        'tenant_id' => $tenantId,
                        'account_id' => $mapping->inventory_account_id,
                        'debit' => 0,
                        'credit' => $inventoryTotal,
                        'is_reversal' => false,
                    ],
                ];

                if ($variance > 0) {
                    $journalItems[] = [
                        'id' => (string) Str::uuid(),
                        'journal_entry_id' => $journalEntryId,
                        'tenant_id' => $tenantId,
                        'account_id' => $mapping->cogs_account_id,
                        'debit' => 0,
                        'credit' => $variance,
                        'is_reversal' => false,
                    ];
                } elseif ($variance < 0) {
                    $journalItems[] = [
                        'id' => (string) Str::uuid(),
                        'journal_entry_id' => $journalEntryId,
                        'tenant_id' => $tenantId,
                        'account_id' => $mapping->cogs_account_id,
                        'debit' => abs($variance),
                        'credit' => 0,
                        'is_reversal' => false,
                    ];
                }

                JournalItem::query()->insert($journalItems);

                return [
                    'purchase_return_id' => $returnId,
                ];
            });
        } catch (RuntimeException $exception) {
            return response()->json([
                'code' => 'PURCHASE_RETURN_FAILED',
                'message' => $exception->getMessage(),
                'details' => [],
            ], 422);
        }

        event(new PurchaseReturned(
            purchaseReturnId: $result['purchase_return_id'],
            purchaseId: $validated['purchase_id'],
            tenantId: $tenantId,
            total: $total,
            actorId: $request->user()?->id,
            occurredAt: $now->toIso8601String()
        ));

        return response()->json($result, 201);
    }
}
