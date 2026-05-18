<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\LockPeriodException;
use App\Http\Controllers\Controller;
use App\Models\AccountMapping;
use App\Models\Location;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Models\Supplier;
use App\Services\AnomalyService;
use App\Services\PurchaseService;
use App\Services\TransactionDateService;
use App\Support\PurchaseFlowResolver;
use App\Support\PurchaseMode;
use App\Support\PurchaseStatus;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use RuntimeException;

class PurchaseController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = TenantContext::id();
        $flowResolver = app(PurchaseFlowResolver::class);
        $tenantPurchaseMode = TenantContext::get()?->purchase_mode;

        $purchases = Purchase::query()
            ->leftJoin('suppliers', function ($join) {
                $join->on('suppliers.id', '=', 'purchases.supplier_id')
                    ->on('suppliers.tenant_id', '=', 'purchases.tenant_id');
            })
            ->leftJoin('locations', function ($join) {
                $join->on('locations.id', '=', 'purchases.location_id')
                    ->on('locations.tenant_id', '=', 'purchases.tenant_id');
            })
            ->where('purchases.tenant_id', $tenantId)
            ->orderByDesc('purchases.created_at')
            ->orderByDesc('purchases.id')
            ->limit(max(1, min((int) $request->integer('limit', 100), 200)))
            ->get([
                'purchases.id',
                'purchases.supplier_id',
                'purchases.location_id',
                'purchases.total',
                'purchases.status',
                'purchases.payment_method',
                'purchases.cash_account_id',
                'purchases.purchase_mode',
                'purchases.due_date',
                'purchases.notes',
                'purchases.transaction_date',
                'purchases.created_at',
                'suppliers.name as supplier_name',
                'locations.name as location_name',
            ])
            ->map(function ($purchase) use ($flowResolver, $tenantPurchaseMode) {
                return [
                    'id' => $purchase->id,
                    'reference' => $this->purchaseReference($purchase->id),
                    'supplier_id' => $purchase->supplier_id,
                    'supplier_name' => $purchase->supplier_name,
                    'location_id' => $purchase->location_id,
                    'location_name' => $purchase->location_name,
                    'total' => (float) $purchase->total,
                    'status' => $purchase->status,
                    'payment_method' => $purchase->payment_method,
                    'cash_account_id' => $purchase->cash_account_id,
                    'purchase_mode' => $flowResolver->resolveMode($purchase->purchase_mode, $tenantPurchaseMode),
                    'due_date' => $purchase->due_date,
                    'notes' => $purchase->notes,
                    'transaction_date' => $purchase->transaction_date,
                    'created_at' => $purchase->created_at,
                ];
            })
            ->values();

        return response()->json([
            'purchases' => $purchases,
        ]);
    }

    public function show(Request $request, string $purchase)
    {
        $tenantId = TenantContext::id();
        $flowResolver = app(PurchaseFlowResolver::class);
        $tenantPurchaseMode = TenantContext::get()?->purchase_mode;

        $purchaseRow = Purchase::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $purchase)
            ->firstOrFail();

        $location = Location::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $purchaseRow->location_id)
            ->first();

        $supplier = Supplier::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $purchaseRow->supplier_id)
            ->first();

        $items = PurchaseItem::query()
            ->join('products', 'products.id', '=', 'purchase_items.product_id')
            ->leftJoin('product_variants', 'product_variants.id', '=', 'purchase_items.product_variant_id')
            ->select([
                'purchase_items.id',
                'purchase_items.qty',
                'purchase_items.received_qty',
                'purchase_items.price',
                'purchase_items.product_variant_id',
                'products.id as product_id',
                'products.name as product_name',
                'products.sku as product_sku',
                'products.image_url as product_image',
                'product_variants.name as product_variant_name',
                'product_variants.sku as product_variant_sku',
            ])
            ->where('purchase_items.tenant_id', $tenantId)
            ->where('purchase_items.purchase_id', $purchaseRow->id)
            ->get();

        return response()->json([
            'purchase' => [
                'id' => $purchaseRow->id,
                'total' => (float) $purchaseRow->total,
                'status' => $purchaseRow->status,
                'payment_method' => $purchaseRow->payment_method,
                'cash_account_id' => $purchaseRow->cash_account_id,
                'purchase_mode' => $flowResolver->resolveMode($purchaseRow->purchase_mode, $tenantPurchaseMode),
                'due_date' => $purchaseRow->due_date,
                'location_id' => $purchaseRow->location_id,
                'location_name' => $location?->name,
                'notes' => $purchaseRow->notes,
                'created_at' => $purchaseRow->created_at,
            ],
            'supplier' => $supplier ? [
                'id' => $supplier->id,
                'name' => $supplier->name,
            ] : null,
            'items' => $items,
            'receipts' => $this->resolveReceiptHistory($tenantId, $purchaseRow->id),
            'flow' => [
                'purchase_mode' => $flowResolver->resolveMode($purchaseRow->purchase_mode, $tenantPurchaseMode),
                'can_approve' => $flowResolver->canApprove((string) $purchaseRow->status, $purchaseRow->purchase_mode, $tenantPurchaseMode),
                'can_receive' => $flowResolver->canReceive((string) $purchaseRow->status, $purchaseRow->purchase_mode, $tenantPurchaseMode),
                'can_close' => $flowResolver->canClose((string) $purchaseRow->status, $purchaseRow->purchase_mode, $tenantPurchaseMode),
            ],
        ]);
    }

    private function resolveReceiptHistory(?string $tenantId, string $purchaseId): array
    {
        if (! $tenantId) {
            return [];
        }

        $rows = DB::table('stock_ledger as sl')
            ->join('purchase_items as pi', function ($join) {
                $join->on('pi.id', '=', 'sl.reference_item_id')
                    ->on('pi.tenant_id', '=', 'sl.tenant_id');
            })
            ->join('products as p', function ($join) {
                $join->on('p.id', '=', 'pi.product_id')
                    ->on('p.tenant_id', '=', 'pi.tenant_id');
            })
            ->leftJoin('product_variants as pv', function ($join) {
                $join->on('pv.id', '=', 'pi.product_variant_id')
                    ->on('pv.tenant_id', '=', 'pi.tenant_id');
            })
            ->leftJoin('users as u', 'u.id', '=', 'sl.created_by')
            ->where('sl.tenant_id', $tenantId)
            ->where('sl.reference_type', 'purchase')
            ->where('sl.reference_id', $purchaseId)
            ->orderBy('sl.created_at')
            ->orderBy('p.name')
            ->select([
                'sl.created_at',
                'sl.created_by',
                'u.name as created_by_name',
                'sl.qty_in',
                'sl.unit_cost',
                'sl.total_cost',
                'pi.product_id',
                'pi.product_variant_id',
                'pi.qty as ordered_qty',
                'p.name as product_name',
                'p.sku as product_sku',
                'pv.name as product_variant_name',
                'pv.sku as product_variant_sku',
            ])
            ->get();

        $grouped = [];

        foreach ($rows as $row) {
            $groupKey = (string) $row->created_at.'|'.(string) ($row->created_by ?? '');

            if (! isset($grouped[$groupKey])) {
                $grouped[$groupKey] = [
                    'received_at' => $row->created_at,
                    'received_by' => $row->created_by,
                    'received_by_name' => $row->created_by_name ?: 'Sistem',
                    'total_qty' => 0.0,
                    'items' => [],
                ];
            }

            $grouped[$groupKey]['total_qty'] += (float) $row->qty_in;
            $grouped[$groupKey]['items'][] = [
                'product_id' => $row->product_id,
                    'product_variant_id' => $row->product_variant_id,
                'product_name' => $row->product_name,
                'product_sku' => $row->product_sku,
                    'product_variant_name' => $row->product_variant_name,
                    'product_variant_sku' => $row->product_variant_sku,
                'ordered_qty' => (float) $row->ordered_qty,
                'received_qty' => (float) $row->qty_in,
                'unit_cost' => (float) $row->unit_cost,
                'total_cost' => (float) $row->total_cost,
            ];
        }

        return array_values($grouped);
    }

    public function store(Request $request)
    {
        $tenantId = TenantContext::id();

        $validated = $request->validate([
            'supplier_id' => ['required', 'uuid', $tenantId
                ? Rule::exists('suppliers', 'id')->where('tenant_id', $tenantId)
                : 'exists:suppliers,id'],
            'location_id' => ['nullable', 'uuid', $tenantId
                ? Rule::exists('locations', 'id')->where('tenant_id', $tenantId)
                : 'exists:locations,id'],
            'transaction_date' => ['nullable', 'date'],
            'payment_method' => ['nullable', 'in:tunai,tempo'],
            'cash_account_id' => ['nullable', 'uuid', $tenantId
                ? Rule::exists('chart_of_accounts', 'id')->where('tenant_id', $tenantId)
                : 'exists:chart_of_accounts,id'],
            'due_date' => ['nullable', 'date'],
            'purchase_mode' => ['nullable', 'string', Rule::in(PurchaseMode::all())],
            'status' => ['nullable', 'string', Rule::in(PurchaseStatus::createInputs())],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'uuid', $tenantId
                ? Rule::exists('products', 'id')->where('tenant_id', $tenantId)
                : 'exists:products,id'],
            'items.*.qty' => ['required', 'numeric', 'min:0.01'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
        ]);
        $purchaseModeProvided = array_key_exists('purchase_mode', $validated);

        $location = null;
        if (! empty($validated['location_id'])) {
            $location = Location::query()
                ->where('tenant_id', $tenantId)
                ->where('id', $validated['location_id'])
                ->first();
        }

        if (! $location) {
            $location = Location::query()
                ->where('tenant_id', $tenantId)
                ->where('is_default', true)
                ->first();
        }

        if (! $location) {
            $location = Location::query()
                ->where('tenant_id', $tenantId)
                ->orderBy('name')
                ->first();
        }

        if (! $location) {
            return response()->json([
                'code' => 'LOCATION_NOT_FOUND',
                'message' => 'Lokasi stok tidak ditemukan.',
                'details' => [],
            ], 422);
        }

        $total = collect($validated['items'])->sum(function ($item) {
            return (float) $item['qty'] * (float) $item['price'];
        });

        try {
            $transactionDate = (new TransactionDateService())
                ->resolve($validated['transaction_date'] ?? null);
        } catch (InvalidArgumentException $exception) {
            if ($tenantId) {
                app(AnomalyService::class)->raise(
                    tenantId: $tenantId,
                    severity: 'low',
                    type: 'invalid_transaction_date_attempt',
                    payload: [
                        'source' => 'purchase_create',
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

        $paymentMethod = $validated['payment_method'] ?? 'tempo';
        $dueDate = $validated['due_date'] ?? null;
        $cashAccountId = $validated['cash_account_id'] ?? null;
        $purchaseMode = app(PurchaseFlowResolver::class)->resolveMode(
            $validated['purchase_mode'] ?? null,
            TenantContext::get()?->purchase_mode
        );
        $status = $validated['status'] ?? PurchaseStatus::DRAFT;

        if (! $purchaseModeProvided && $status === PurchaseStatus::SUBMITTED) {
            $purchaseMode = PurchaseMode::CONTROLLED;
        }

        if ($paymentMethod === 'tempo' && ! $dueDate) {
            $dueDate = now('UTC')->addDays(14)->toDateString();
        }

        if ($paymentMethod === 'tunai') {
            $cashAccountId = $cashAccountId ?: $this->resolveDefaultCashAccountId($tenantId);

            if (! $cashAccountId) {
                return response()->json([
                    'code' => 'CASH_ACCOUNT_NOT_CONFIGURED',
                    'message' => 'Akun kas belum diatur. Lengkapi mapping akun kas terlebih dahulu.',
                    'details' => [],
                ], 422);
            }

            $availableCash = $this->resolveCashAccountBalance($tenantId, $cashAccountId, $transactionDate->toDateString());
            if ($availableCash <= 0) {
                $availableCash = $this->resolveEstimatedAvailableCash($tenantId);
            }

            if ($total > $availableCash) {
                return response()->json([
                    'code' => 'INSUFFICIENT_CASH_BALANCE',
                    'message' => 'Saldo kas akun tidak cukup untuk pembelian tunai.',
                    'details' => [
                        'available_cash' => round($availableCash, 2),
                        'required_amount' => round($total, 2),
                    ],
                ], 422);
            }
        }

        $purchasePayload = [
            'supplier_id' => $validated['supplier_id'],
            'location_id' => $location?->id,
            'total' => $total,
            'payment_method' => $paymentMethod,
            'due_date' => $dueDate,
            'cash_account_id' => Schema::hasColumn('purchases', 'cash_account_id') ? $cashAccountId : null,
            'notes' => $validated['notes'] ?? null,
            'transaction_date' => $transactionDate->toDateString(),
            'items' => $validated['items'],
            'purchase_mode' => $purchaseMode,
        ];

        try {
            if ($status === PurchaseStatus::DRAFT) {
                $result = $this->purchaseService()->createDraftPurchase($purchasePayload, $request->user()?->id);
            } elseif ($purchaseMode === PurchaseMode::SIMPLE) {
                $result = $this->purchaseService()->createDirectPurchase($purchasePayload, $request->user()?->id);
            } elseif ($purchaseMode === PurchaseMode::PO_SHORT) {
                $result = $this->purchaseService()->createShortPO($purchasePayload, $request->user()?->id);
            } else {
                $result = $this->purchaseService()->createControlledPO($purchasePayload, $request->user()?->id);
            }

            return response()->json($result, 201);
        } catch (RuntimeException $exception) {
            return response()->json([
                'code' => 'PURCHASE_CREATE_FAILED',
                'message' => $exception->getMessage(),
                'details' => [],
            ], 422);
        }
    }

    private function resolveEstimatedAvailableCash(?string $tenantId): float
    {
        if (! $tenantId) {
            return 0.0;
        }

        $openShift = Shift::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'open')
            ->orderByDesc('opened_at')
            ->first();

        if (! $openShift) {
            return 0.0;
        }

        $saleIds = Sale::query()
            ->where('tenant_id', $tenantId)
            ->where('shift_id', $openShift->id)
            ->where('status', 'completed')
            ->pluck('id');

        $cashIn = 0.0;
        if ($saleIds->isNotEmpty()) {
            $cashIn = (float) Payment::query()
                ->whereIn('sale_id', $saleIds)
                ->where('method', 'cash')
                ->sum('amount');
        }

        $cashOut = (float) DB::table('purchases')
            ->where('tenant_id', $tenantId)
            ->where('payment_method', 'tunai')
            ->where('status', '!=', PurchaseStatus::CANCELLED)
            ->where('created_at', '>=', $openShift->opened_at)
            ->sum('total');

        $openingCash = (float) $openShift->opening_cash;
        $available = $openingCash + $cashIn - $cashOut;

        return max(0.0, round($available, 2));
    }

    private function resolveCashAccountBalance(?string $tenantId, ?string $cashAccountId, string $asOfDate): float
    {
        if (! $tenantId || ! $cashAccountId) {
            return 0.0;
        }

        $row = DB::table('journal_items as ji')
            ->join('journal_entries as je', function ($join) {
                $join->on('je.id', '=', 'ji.journal_entry_id')
                    ->on('je.tenant_id', '=', 'ji.tenant_id');
            })
            ->where('ji.tenant_id', $tenantId)
            ->where('ji.account_id', $cashAccountId)
            ->where('je.status', 'posted')
            ->where('je.date', '<=', $asOfDate)
            ->selectRaw('COALESCE(SUM(ji.debit - ji.credit), 0) as balance')
            ->first();

        return max(0.0, (float) ($row->balance ?? 0));
    }

    private function resolveDefaultCashAccountId(?string $tenantId): ?string
    {
        if (! $tenantId) {
            return null;
        }

        $mapping = AccountMapping::query()
            ->where('tenant_id', $tenantId)
            ->first(['cash_account_id']);

        return $mapping?->cash_account_id;
    }

    public function approve(Request $request, string $purchase, PurchaseService $service)
    {
        try {
            $service->approve($purchase, $request->user()?->id);

            return response()->json([
                'status' => PurchaseStatus::APPROVED,
            ]);
        } catch (RuntimeException $exception) {
            return response()->json([
                'code' => 'PURCHASE_APPROVE_FAILED',
                'message' => $exception->getMessage(),
                'details' => [],
            ], 422);
        }
    }

    public function cancel(Request $request, string $purchase, PurchaseService $service)
    {
        try {
            $service->cancel($purchase, $request->user()?->id);

            return response()->json([
                'status' => PurchaseStatus::CANCELLED,
            ]);
        } catch (RuntimeException $exception) {
            return response()->json([
                'code' => 'PURCHASE_CANCEL_FAILED',
                'message' => $exception->getMessage(),
                'details' => [],
            ], 422);
        }
    }

    public function decide(Request $request, string $purchase)
    {
        $tenantId = TenantContext::id();

        $validated = $request->validate([
            'status' => ['required', Rule::in([PurchaseStatus::APPROVED, PurchaseStatus::CANCELLED])],
        ]);

        $service = app(PurchaseService::class);

        try {
            if ($validated['status'] === PurchaseStatus::APPROVED) {
                $service->approve($purchase, $request->user()?->id);
            } else {
                $service->cancel($purchase, $request->user()?->id);
            }
        } catch (RuntimeException $exception) {
            return response()->json([
                'code' => 'PURCHASE_DECISION_FAILED',
                'message' => $exception->getMessage(),
                'details' => [],
            ], 422);
        }

        $purchaseRow = Purchase::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $purchase)
            ->firstOrFail();

        return response()->json([
            'status' => $purchaseRow->status,
        ]);
    }

    private function purchaseService(): PurchaseService
    {
        return app(PurchaseService::class);
    }

    private function purchaseReference(string $purchaseId): string
    {
        return 'PO-'.strtoupper(Str::substr($purchaseId, 0, 6));
    }

    public function receive(Request $request, string $purchase, PurchaseService $service)
    {
        $tenantId = TenantContext::id();

        $payload = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'transaction_date' => ['nullable', 'date'],
            'items.*.product_id' => ['required', 'uuid', $tenantId
                ? Rule::exists('products', 'id')->where('tenant_id', $tenantId)
                : 'exists:products,id'],
            'items.*.qty' => ['required', 'numeric', 'min:0.01'],
        ]);

        try {
            $result = $service->receive($purchase, $payload, $request->user()?->id);

            return response()->json($result);
        } catch (LockPeriodException $exception) {
            return response()->json([
                'code' => $exception->errorCode(),
                'message' => $exception->getMessage(),
                'details' => [],
            ], $exception->status());
        } catch (InvalidArgumentException $exception) {
            if ($tenantId) {
                app(AnomalyService::class)->raise(
                    tenantId: $tenantId,
                    severity: 'low',
                    type: 'invalid_transaction_date_attempt',
                    payload: [
                        'source' => 'purchase_receive',
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
                'code' => 'PURCHASE_RECEIVE_FAILED',
                'message' => $exception->getMessage(),
                'details' => [],
            ], 422);
        }
    }

    public function close(Request $request, string $purchase, PurchaseService $service)
    {
        try {
            $service->close($purchase, $request->user()?->id);

            return response()->json([
                'status' => PurchaseStatus::CLOSED,
            ]);
        } catch (RuntimeException $exception) {
            return response()->json([
                'code' => 'PURCHASE_CLOSE_FAILED',
                'message' => $exception->getMessage(),
                'details' => [],
            ], 422);
        }
    }

    public function updatePayment(Request $request, string $purchase)
    {
        $tenantId = TenantContext::id();

        $validated = $request->validate([
            'payment_method' => ['nullable', 'in:tunai,tempo'],
            'due_date' => ['nullable', 'date'],
            'cash_account_id' => ['nullable', 'uuid', $tenantId
                ? Rule::exists('chart_of_accounts', 'id')->where('tenant_id', $tenantId)
                : 'exists:chart_of_accounts,id'],
        ]);

        try {
            $updated = $this->purchaseService()->updatePayment($purchase, $validated, $request->user()?->id);

            return response()->json([
                'purchase' => $updated,
            ]);
        } catch (RuntimeException $exception) {
            $code = $exception->getMessage() === 'Akun kas belum diatur. Lengkapi mapping akun kas terlebih dahulu.'
                ? 'CASH_ACCOUNT_NOT_CONFIGURED'
                : 'PURCHASE_PAYMENT_UPDATE_FAILED';

            return response()->json([
                'code' => $code,
                'message' => $exception->getMessage(),
                'details' => [],
            ], 422);
        }
    }

    public function updateItemPrice(Request $request, string $purchase, string $item, PurchaseService $service)
    {
        $validated = $request->validate([
            'price' => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $result = $service->updateItemPrice($purchase, $item, (float) $validated['price']);

            return response()->json([
                'purchase' => [
                    'id' => $result['purchase']->id,
                    'total' => (float) $result['purchase']->total,
                    'status' => $result['purchase']->status,
                ],
                'item' => [
                    'id' => $result['item']->id,
                    'price' => (float) $result['item']->price,
                ],
            ]);
        } catch (RuntimeException $exception) {
            return response()->json([
                'code' => 'PURCHASE_ITEM_PRICE_UPDATE_FAILED',
                'message' => $exception->getMessage(),
                'details' => [],
            ], 422);
        }
    }
}