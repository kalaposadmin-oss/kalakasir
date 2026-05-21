<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\PurchaseReturnValuationException;
use App\Http\Controllers\Controller;
use App\Models\AccountMapping;
use App\Models\InventoryBalance;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\StockLedger;
use App\Exceptions\LockPeriodException;
use App\Services\LockPeriodService;
use App\Services\InventoryValuationService;
use App\Events\PurchaseReturned;
use App\Services\AnomalyService;
use App\Services\TransactionDateService;
use App\Support\Decimal;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use RuntimeException;

class PurchaseReturnController extends Controller
{
    public function store(Request $request)
    {
        $tenantId = TenantContext::id();

        $validated = $request->validate([
            'purchase_id' => ['required', 'uuid', $tenantId
                ? Rule::exists('purchases', 'id')->where('tenant_id', $tenantId)
                : 'exists:purchases,id'],
            'supplier_id' => ['required', 'uuid', $tenantId
                ? Rule::exists('suppliers', 'id')->where('tenant_id', $tenantId)
                : 'exists:suppliers,id'],
            'transaction_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_item_id' => ['required', 'uuid', $tenantId
                ? Rule::exists('purchase_items', 'id')->where('tenant_id', $tenantId)
                : 'exists:purchase_items,id'],
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

        $total = collect($validated['items'])->reduce(function (string $carry, array $item) {
            return Decimal::add($carry, Decimal::multiply($item['qty'], $item['price'], Decimal::MONEY_SCALE), Decimal::MONEY_SCALE);
        }, Decimal::money('0'));

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
                $purchaseItems = $this->loadPurchaseItemsForReturn($tenantId, $purchase->id, Arr::pluck($validated['items'], 'purchase_item_id'));
                $alreadyReturned = DB::table('purchase_return_items')
                    ->join('purchase_returns', function ($join): void {
                        $join->on('purchase_returns.id', '=', 'purchase_return_items.purchase_return_id')
                            ->on('purchase_returns.tenant_id', '=', 'purchase_return_items.tenant_id');
                    })
                    ->where('purchase_return_items.tenant_id', $tenantId)
                    ->where('purchase_returns.purchase_id', $purchase->id)
                    ->whereIn('purchase_return_items.purchase_item_id', Arr::pluck($validated['items'], 'purchase_item_id'))
                    ->select('purchase_return_items.purchase_item_id')
                    ->selectRaw('coalesce(sum(purchase_return_items.qty), 0) as returned_qty')
                    ->groupBy('purchase_return_items.purchase_item_id')
                    ->pluck('returned_qty', 'purchase_item_id');

                $returnId = (string) Str::uuid();
                $items = collect($validated['items'])->map(function ($item) use ($tenantId, $returnId, $purchaseItems, $alreadyReturned) {
                    $purchaseItem = $purchaseItems->get((string) $item['purchase_item_id']);

                    if (! $purchaseItem) {
                        throw new RuntimeException('Item retur harus berasal dari purchase yang sama.');
                    }

                    $receivedQty = Decimal::qty($purchaseItem->received_qty ?? $purchaseItem->qty ?? 0);
                    $returnedQty = Decimal::qty($alreadyReturned[(string) $purchaseItem->id] ?? 0);
                    $remainingQty = Decimal::subtract($receivedQty, $returnedQty, Decimal::QTY_SCALE);

                    if (Decimal::compare(Decimal::qty($item['qty']), $remainingQty) > 0) {
                        throw new RuntimeException('Qty retur melebihi jumlah barang yang sudah diterima dan belum pernah diretur.');
                    }

                    return [
                        'id' => (string) Str::uuid(),
                        'tenant_id' => $tenantId,
                        'purchase_return_id' => $returnId,
                        'purchase_item_id' => (string) $purchaseItem->id,
                        'product_id' => (string) $purchaseItem->product_id,
                        'product_variant_id' => $purchaseItem->product_variant_id ? (string) $purchaseItem->product_variant_id : null,
                        'qty' => Decimal::float(Decimal::qty($item['qty']), Decimal::QTY_SCALE),
                        'price' => Decimal::float(Decimal::money($item['price']), Decimal::MONEY_SCALE),
                        'reason' => $item['reason'] ?? null,
                    ];
                })->all();

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

                PurchaseReturnItem::query()->insert($items);

                $ledgerRows = [];
                $totalValuationCost = Decimal::money('0');

                foreach ($items as $item) {
                    $qty = Decimal::qty($item['qty']);
                    $productVariantId = $item['product_variant_id'] ? (string) $item['product_variant_id'] : null;

                    $affected = $this->decrementInventory(
                        tenantId: $tenantId,
                        productId: (string) $item['product_id'],
                        productVariantId: $productVariantId,
                        locationId: (string) $purchase->location_id,
                        qty: Decimal::float($qty, Decimal::QTY_SCALE)
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
                                'requested_qty' => Decimal::float($qty, Decimal::QTY_SCALE),
                            ],
                            referenceId: $returnId
                        );
                        throw new RuntimeException('Stok tidak mencukupi untuk retur.');
                    }

                    $balanceAfter = Decimal::qty(InventoryBalance::query()
                        ->where('tenant_id', $tenantId)
                        ->where('product_id', $item['product_id'])
                        ->where('location_id', $purchase->location_id)
                        ->when($productVariantId, function ($query) use ($productVariantId) {
                            $query->where('product_variant_id', $productVariantId);
                        }, function ($query) {
                            $query->whereNull('product_variant_id');
                        })
                        ->value('qty_on_hand'));

                    $ledgerRows[] = [
                        'id' => (string) Str::uuid(),
                        'tenant_id' => $tenantId,
                        'product_id' => $item['product_id'],
                        'product_variant_id' => $productVariantId,
                        'location_id' => $purchase->location_id,
                        'reference_type' => 'purchase_return',
                        'reference_id' => $returnId,
                        'reference_item_id' => $item['id'],
                        'qty_in' => 0,
                        'qty_out' => Decimal::float($qty, Decimal::QTY_SCALE),
                        'balance_after' => Decimal::float($balanceAfter, Decimal::QTY_SCALE),
                        'created_at' => $now,
                        'created_by' => $request->user()?->id,
                    ];

                    $valuationResult = $valuationService->issue(
                        tenantId: $tenantId,
                        productId: $item['product_id'],
                        productVariantId: $productVariantId,
                        locationId: $purchase->location_id,
                        qty: Decimal::float($qty, Decimal::QTY_SCALE),
                        referenceType: 'purchase_return',
                        referenceId: $returnId,
                        referenceItemId: $item['id'],
                        at: $now
                    );

                    $totalValuationCost = Decimal::add($totalValuationCost, Decimal::money($valuationResult['total_cost'] ?? 0), Decimal::MONEY_SCALE);
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

                $documentTotal = Decimal::money($total);
                $inventoryTotal = Decimal::money($totalValuationCost);
                $variance = Decimal::subtract($documentTotal, $inventoryTotal, Decimal::MONEY_SCALE);

                $journalItems = [
                    [
                        'id' => (string) Str::uuid(),
                        'journal_entry_id' => $journalEntryId,
                        'tenant_id' => $tenantId,
                        'account_id' => $mapping->payable_account_id,
                        'debit' => Decimal::float($documentTotal, Decimal::MONEY_SCALE),
                        'credit' => 0,
                        'is_reversal' => false,
                    ],
                    [
                        'id' => (string) Str::uuid(),
                        'journal_entry_id' => $journalEntryId,
                        'tenant_id' => $tenantId,
                        'account_id' => $mapping->inventory_account_id,
                        'debit' => 0,
                        'credit' => Decimal::float($inventoryTotal, Decimal::MONEY_SCALE),
                        'is_reversal' => false,
                    ],
                ];

                if (Decimal::compare($variance, '0') > 0) {
                    $journalItems[] = [
                        'id' => (string) Str::uuid(),
                        'journal_entry_id' => $journalEntryId,
                        'tenant_id' => $tenantId,
                        'account_id' => $mapping->cogs_account_id,
                        'debit' => 0,
                        'credit' => Decimal::float($variance, Decimal::MONEY_SCALE),
                        'is_reversal' => false,
                    ];
                } elseif (Decimal::compare($variance, '0') < 0) {
                    $journalItems[] = [
                        'id' => (string) Str::uuid(),
                        'journal_entry_id' => $journalEntryId,
                        'tenant_id' => $tenantId,
                        'account_id' => $mapping->cogs_account_id,
                        'debit' => Decimal::float(Decimal::abs($variance, Decimal::MONEY_SCALE), Decimal::MONEY_SCALE),
                        'credit' => 0,
                        'is_reversal' => false,
                    ];
                }

                JournalItem::query()->insert($journalItems);

                return [
                    'purchase_return_id' => $returnId,
                ];
            });
        } catch (PurchaseReturnValuationException $exception) {
            app(\App\Services\ValuationAuditService::class)->log($tenantId, $exception->type(), $exception->payload());
            app(AnomalyService::class)->raise(
                tenantId: $tenantId,
                severity: 'high',
                type: $exception->type(),
                payload: $exception->payload(),
                referenceId: $exception->referenceId(),
            );

            return response()->json([
                'code' => 'PURCHASE_RETURN_FAILED',
                'message' => $exception->getMessage(),
                'details' => [],
            ], 422);
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

    private function loadPurchaseItemsForReturn(string $tenantId, string $purchaseId, array $purchaseItemIds): Collection
    {
        return PurchaseItem::query()
            ->where('tenant_id', $tenantId)
            ->where('purchase_id', $purchaseId)
            ->whereIn('id', $purchaseItemIds)
            ->get(['id', 'product_id', 'product_variant_id', 'qty', 'received_qty'])
            ->keyBy('id');
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
}
