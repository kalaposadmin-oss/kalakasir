<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\Supplier;
use App\Exceptions\LockPeriodException;
use App\Services\AnomalyService;
use App\Services\PurchaseService;
use App\Services\TransactionDateService;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use RuntimeException;

class PurchaseController extends Controller
{
    public function index()
    {
        $tenantId = TenantContext::id();

        $purchases = Purchase::query()
            ->where('purchases.tenant_id', $tenantId)
            ->leftJoin('suppliers', function ($join) {
                $join->on('purchases.supplier_id', '=', 'suppliers.id')
                    ->on('purchases.tenant_id', '=', 'suppliers.tenant_id');
            })
            ->select([
                'purchases.id',
                'purchases.total',
                'purchases.status',
                'purchases.payment_method',
                'purchases.due_date',
                'purchases.created_at',
                'suppliers.name as supplier_name',
            ])
            ->orderByDesc('purchases.created_at')
            ->get();

        return response()->json([
            'purchases' => $purchases,
        ]);
    }

    public function show(Request $request, string $purchase)
    {
        $tenantId = TenantContext::id();

        $purchaseRow = Purchase::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $purchase)
            ->firstOrFail();

        $supplier = Supplier::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $purchaseRow->supplier_id)
            ->first();

        $items = PurchaseItem::query()
            ->join('products', 'products.id', '=', 'purchase_items.product_id')
            ->select([
                'purchase_items.id',
                'purchase_items.qty',
                'purchase_items.received_qty',
                'purchase_items.price',
                'products.id as product_id',
                'products.name as product_name',
                'products.sku as product_sku',
                'products.image_url as product_image',
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
                'due_date' => $purchaseRow->due_date,
                'location_id' => $purchaseRow->location_id,
                'notes' => $purchaseRow->notes,
                'created_at' => $purchaseRow->created_at,
            ],
            'supplier' => $supplier ? [
                'id' => $supplier->id,
                'name' => $supplier->name,
            ] : null,
            'items' => $items,
        ]);
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
            'due_date' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'uuid', $tenantId
                ? Rule::exists('products', 'id')->where('tenant_id', $tenantId)
                : 'exists:products,id'],
            'items.*.qty' => ['required', 'numeric', 'min:0.01'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
        ]);

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

        $paymentMethod = $validated['payment_method'] ?? 'tempo';
        $dueDate = $validated['due_date'] ?? null;

        if ($paymentMethod === 'tempo' && ! $dueDate) {
            $dueDate = now('UTC')->addDays(14)->toDateString();
        }

        if ($paymentMethod === 'tunai') {
            $availableCash = $this->resolveEstimatedAvailableCash($tenantId);
            if ($total > $availableCash) {
                return response()->json([
                    'code' => 'INSUFFICIENT_CASH_BALANCE',
                    'message' => 'Saldo kas tidak cukup untuk pembelian tunai.',
                    'details' => [
                        'available_cash' => round($availableCash, 2),
                        'required_amount' => round($total, 2),
                    ],
                ], 422);
            }
        }

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

        $purchaseId = (string) Str::uuid();
        Purchase::query()->create([
            'id' => $purchaseId,
            'tenant_id' => $tenantId,
            'supplier_id' => $validated['supplier_id'],
            'location_id' => $location?->id,
            'total' => $total,
            'status' => $validated['status'] ?? 'draft',
            'payment_method' => $paymentMethod,
            'due_date' => $dueDate,
            'notes' => $validated['notes'] ?? null,
            'created_at' => now('UTC'),
            'transaction_date' => $transactionDate->toDateString(),
        ]);

        $items = collect($validated['items'])->map(function ($item) use ($tenantId, $purchaseId) {
            return [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'purchase_id' => $purchaseId,
                'product_id' => $item['product_id'],
                'qty' => (float) $item['qty'],
                'price' => (float) $item['price'],
            ];
        })->all();

        PurchaseItem::query()->insert($items);

        return response()->json([
            'purchase_id' => $purchaseId,
        ], 201);
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
            ->where('status', '!=', 'cancelled')
            ->where('created_at', '>=', $openShift->opened_at)
            ->sum('total');

        $openingCash = (float) $openShift->opening_cash;
        $available = $openingCash + $cashIn - $cashOut;

        return max(0.0, round($available, 2));
    }

    public function decide(Request $request, string $purchase)
    {
        $tenantId = TenantContext::id();

        $validated = $request->validate([
            'status' => ['required', 'in:approved,cancelled,rejected'],
        ]);

        $service = app(PurchaseService::class);

        try {
            if ($validated['status'] === 'approved') {
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

    public function receive(Request $request, string $purchase, PurchaseService $service)
    {
        $tenantId = TenantContext::id();

        $payload = $request->validate([
            'items' => ['nullable', 'array'],
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
                'status' => 'closed',
            ]);
        } catch (RuntimeException $exception) {
            return response()->json([
                'code' => 'PURCHASE_CLOSE_FAILED',
                'message' => $exception->getMessage(),
                'details' => [],
            ], 422);
        }
    }
}
