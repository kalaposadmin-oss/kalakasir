<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerCategory;
use App\Models\Sale;
use App\Services\CustomerOrderLinkService;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function __construct(
        private readonly CustomerOrderLinkService $orderLinks,
    ) {
    }

    public function index(Request $request)
    {
        $tenantId = TenantContext::id();

        $liteRequested = $request->boolean('lite') || $request->filled('q') || $request->filled('limit');

        if ($liteRequested) {
            $limit = max(1, min((int) $request->query('limit', 100), 250));
            $term = trim((string) $request->query('q', ''));

            $rows = Customer::query()
                ->when($tenantId, fn ($query) => $query->where('customers.tenant_id', $tenantId))
                ->when($term !== '', function ($query) use ($term) {
                    $query->where(function ($nested) use ($term) {
                        $likeTerm = '%'.$term.'%';
                        $nested->where('customers.name', 'like', $likeTerm)
                            ->orWhere('customers.phone', 'like', $likeTerm);
                    });
                })
                ->orderBy('customers.name')
                ->limit($limit)
                ->get([
                    'customers.id',
                    'customers.name',
                ]);

            return response()->json([
                'customers' => $rows,
                'total' => $rows->count(),
                'filters' => [
                    'q' => $term,
                    'limit' => $limit,
                ],
            ]);
        }

        $customers = Customer::query()
            ->when($tenantId, fn ($query) => $query->where('customers.tenant_id', $tenantId))
            ->leftJoin('sales', function ($join) {
                $join->on('customers.id', '=', 'sales.customer_id')
                    ->on('customers.tenant_id', '=', 'sales.tenant_id')
                    ->where('sales.status', '=', 'completed');
            })
            ->leftJoin('customer_categories', function ($join) {
                $join->on('customers.customer_category_id', '=', 'customer_categories.id')
                    ->on('customers.tenant_id', '=', 'customer_categories.tenant_id');
            })
            ->select([
                'customers.id',
                'customers.name',
                'customers.phone',
                'customers.address',
                'customers.customer_category_id',
                'customer_categories.name as customer_category_name',
                'customer_categories.color as customer_category_color',
                'customers.created_at',
            ])
            ->selectRaw('coalesce(sum(sales.total), 0) as total_spent')
            ->selectRaw('count(sales.id) as order_count')
            ->groupBy(
                'customers.id',
                'customers.name',
                'customers.phone',
                'customers.address',
                'customers.customer_category_id',
                'customer_categories.name',
                'customer_categories.color',
                'customers.created_at'
            )
            ->orderBy('customers.created_at', 'desc')
            ->get();

        $customerIds = $customers->pluck('id')->all();
        $recentSales = [];

        if ($customerIds) {
            $sales = Sale::query()
                ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
                ->whereIn('customer_id', $customerIds)
                ->where('status', 'completed')
                ->orderByDesc('created_at')
                ->get(['id', 'customer_id', 'total', 'created_at']);

            foreach ($sales as $sale) {
                $list = $recentSales[$sale->customer_id] ?? [];
                if (count($list) < 2) {
                    $list[] = $sale;
                }
                $recentSales[$sale->customer_id] = $list;
            }
        }

        $rows = $customers->map(function ($customer) use ($recentSales) {
            $total = (float) $customer->total_spent;
            $tier = $total >= 2000000 ? 'gold' : ($total >= 1000000 ? 'silver' : 'regular');
            $points = (int) floor($total / 2000);

            return [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'address' => $customer->address,
                'customer_category_id' => $customer->customer_category_id,
                'category' => $customer->customer_category_id ? [
                    'id' => $customer->customer_category_id,
                    'name' => $customer->customer_category_name,
                    'color' => $customer->customer_category_color ?: 'slate',
                ] : null,
                'total_spent' => $total,
                'order_count' => (int) $customer->order_count,
                'created_at' => $customer->created_at,
                'tier' => $tier,
                'points' => $points,
                'recent_sales' => collect($recentSales[$customer->id] ?? [])->map(function ($sale) {
                    return [
                        'id' => $sale->id,
                        'total' => (float) $sale->total,
                        'created_at' => $sale->created_at,
                    ];
                })->values(),
            ];
        });

        return response()->json([
            'customers' => $rows,
            'total' => $rows->count(),
        ]);
    }

    public function store(Request $request)
    {
        $tenantId = TenantContext::id();

        if (! $tenantId) {
            return response()->json([
                'message' => 'Tenant tidak ditemukan.',
            ], 422);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:500'],
            'customer_category_id' => ['nullable', 'uuid', Rule::exists('customer_categories', 'id')->where('tenant_id', $tenantId)],
        ]);

        $customer = Customer::query()->create([
            'tenant_id' => $tenantId,
            'name' => trim((string) $validated['name']),
            'phone' => filled($validated['phone'] ?? null) ? trim((string) $validated['phone']) : null,
            'address' => filled($validated['address'] ?? null) ? trim((string) $validated['address']) : null,
            'customer_category_id' => $validated['customer_category_id'] ?? null,
            'created_at' => now(),
        ]);

        return response()->json([
            'message' => 'Pelanggan berhasil ditambahkan.',
            'customer' => $this->customerPayload($customer->refresh()),
        ], 201);
    }

    public function update(Request $request, Customer $customer)
    {
        $tenantId = TenantContext::id()
            ?: (string) ($request->user()?->tenant_id ?? '');

        abort_unless($tenantId && (string) $customer->tenant_id === (string) $tenantId, 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:500'],
            'customer_category_id' => ['nullable', 'uuid', Rule::exists('customer_categories', 'id')->where('tenant_id', $tenantId)],
        ]);

        $customer->forceFill([
            'name' => trim((string) $validated['name']),
            'phone' => filled($validated['phone'] ?? null) ? trim((string) $validated['phone']) : null,
            'address' => filled($validated['address'] ?? null) ? trim((string) $validated['address']) : null,
            'customer_category_id' => $validated['customer_category_id'] ?? null,
        ])->save();

        return response()->json([
            'message' => 'Pelanggan berhasil diperbarui.',
            'customer' => $this->customerPayload($customer->refresh()),
        ]);
    }

    public function orderLink(Request $request, Customer $customer)
    {
        $customer = $this->scopedCustomer($request, $customer);
        $link = $this->orderLinks->issue($customer);

        return $this->orderLinkResponse($customer, $link, $link['generated']
            ? 'Link order permanen pelanggan berhasil dibuat.'
            : 'Link order permanen pelanggan siap digunakan.');
    }

    public function regenerateOrderLink(Request $request, Customer $customer)
    {
        $customer = $this->scopedCustomer($request, $customer);
        $link = $this->orderLinks->issue($customer, regenerate: true);

        return $this->orderLinkResponse(
            $customer,
            $link,
            'Link order baru dibuat. Link lama otomatis tidak berlaku.'
        );
    }

    private function scopedCustomer(Request $request, Customer $customer): Customer
    {
        $tenantId = TenantContext::id()
            ?: (string) ($request->user()?->tenant_id ?? '');

        abort_unless($tenantId && (string) $customer->tenant_id === (string) $tenantId, 404);

        return Customer::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($customer->id)
            ->firstOrFail();
    }

    private function customerPayload(Customer $customer): array
    {
        $category = null;

        if ($customer->customer_category_id) {
            $categoryRow = CustomerCategory::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $customer->tenant_id)
                ->whereKey($customer->customer_category_id)
                ->first(['id', 'name', 'color']);

            if ($categoryRow) {
                $category = [
                    'id' => $categoryRow->id,
                    'name' => $categoryRow->name,
                    'color' => $categoryRow->color ?: 'slate',
                ];
            }
        }

        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'address' => $customer->address,
            'customer_category_id' => $customer->customer_category_id,
            'category' => $category,
            'created_at' => $customer->created_at,
            'total_spent' => 0,
            'order_count' => 0,
            'tier' => 'regular',
            'points' => 0,
            'recent_sales' => [],
        ];
    }

    /**
     * @param  array{url: string, generated: bool, permanent: bool, rotated_at: ?string}  $link
     */
    private function orderLinkResponse(Customer $customer, array $link, string $message)
    {
        return response()->json([
            'message' => $message,
            'order_link' => $link['url'],
            'expires_at' => null,
            'permanent' => true,
            'regenerated' => $link['generated'],
            'token_rotated_at' => $link['rotated_at'],
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'address' => $customer->address,
            ],
        ]);
    }
}
