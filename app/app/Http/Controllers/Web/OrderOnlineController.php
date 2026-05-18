<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\AccountMapping;
use App\Models\Category;
use App\Models\ChartOfAccount;
use App\Models\OnlineOrder;
use App\Models\Product;
use App\Models\Tenant;
use App\Services\AuditLogger;
use App\Services\CustomerOrderLinkService;
use App\Services\ManagerPinAuthorizationService;
use App\Services\OnlineOrderService;
use App\Services\OrderPickupWindowService;
use App\Services\StockAvailabilityService;
use App\Services\WebRouteContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use App\Exceptions\ManagerPinException;

class OrderOnlineController extends Controller
{
    public function __construct(
        private readonly WebRouteContextService $routeContext,
        private readonly StockAvailabilityService $stockAvailability,
        private readonly OnlineOrderService $onlineOrders,
        private readonly CustomerOrderLinkService $customerOrderLinks,
        private readonly ManagerPinAuthorizationService $managerPins,
        private readonly OrderPickupWindowService $pickupWindows,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    /**
     * Display the order catalog page for customers
     */
    public function catalog(Request $request): View
    {
        $payload = $this->basePayload();
        $tenant = $payload['tenant'] ?? null;
        $payload['products'] = $this->loadProducts($tenant);
        $payload['categories'] = $this->loadCategories($payload['tenantId'] ?? null, $payload['products']);
        $payload['prefillCustomer'] = $this->resolveCheckoutPrefillCustomer($request);

        return view('web.order_online.catalog', $payload);
    }

    /**
     * Display the order review page for customers
     */
    public function review(Request $request): View
    {
        return view('web.order_online.review', array_merge($this->basePayload(), [
            'prefillCustomer' => $this->resolveCheckoutPrefillCustomer($request),
        ]));
    }

    public function startForCustomer(Request $request, string $customer): RedirectResponse
    {
        $linkedCustomer = $this->customerOrderLinks->findByToken($customer);

        if (! $linkedCustomer && $request->hasValidSignature()) {
            $linkedCustomer = Customer::query()->find($customer);
        }

        abort_unless($linkedCustomer, 403);

        $this->putPrefillCustomer($request, $linkedCustomer);

        return redirect()->to('/order');
    }

    public function startForCustomerLink(Request $request, string $customerLink): RedirectResponse
    {
        $linkedCustomer = $this->customerOrderLinks->findByCustomerLink($customerLink);

        abort_unless($linkedCustomer, 403);

        $this->putPrefillCustomer($request, $linkedCustomer);

        return redirect()->to('/order');
    }

    private function putPrefillCustomer(Request $request, Customer $customer): void
    {
        $request->session()->put('order_online.tenant_id', (string) $customer->tenant_id);
        $request->session()->put('order_online.prefill_customer', [
            'customer_id' => (string) $customer->id,
            'name' => trim((string) $customer->name),
            'phone' => trim((string) ($customer->phone ?? '')),
            'address' => trim((string) ($customer->address ?? '')),
        ]);
    }

    public function resetCustomer(Request $request): RedirectResponse
    {
        $request->session()->forget('order_online.prefill_customer');

        return redirect()->to('/order');
    }

    public function submit(Request $request): RedirectResponse
    {
        $tenant = $this->resolvePublicTenant();
        abort_unless($tenant, 404);

        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:150'],
            'customer_phone' => ['required', 'string', 'max:40'],
            'fulfillment_type' => ['nullable', 'in:pickup,delivery'],
            'fulfillment_time' => ['nullable', 'string', 'max:80'],
            'address' => ['nullable', 'string'],
            'customer_note' => ['nullable', 'string'],
            'items' => ['required', 'string'],
        ]);

        $items = json_decode($validated['items'], true);
        if (! is_array($items)) {
            throw ValidationException::withMessages([
                'items' => 'Keranjang tidak valid.',
            ]);
        }

        $fulfillmentType = $validated['fulfillment_type'] ?? 'pickup';
        if ($fulfillmentType === 'pickup') {
            $pickupTimeError = $this->pickupWindows->pickupTimeValidationMessage($tenant, $validated['fulfillment_time'] ?? null);
            if ($pickupTimeError) {
                throw ValidationException::withMessages([
                    'fulfillment_time' => $pickupTimeError,
                ]);
            }

            $submittedPickupTime = $this->pickupWindows->submittedTimeLabel($validated['fulfillment_time'] ?? null);
            if ($submittedPickupTime) {
                $validated['fulfillment_time'] = $submittedPickupTime;
            }
        }

        $address = null;
        if ($fulfillmentType === 'delivery') {
            $address = trim((string) ($validated['address'] ?? ''));
            if ($address === '') {
                throw ValidationException::withMessages([
                    'address' => 'Alamat wajib diisi jika pesanan diantar.',
                ]);
            }
        }

        $prefillCustomer = $this->resolveCheckoutPrefillCustomer($request);

        $result = $this->onlineOrders->createOrder($tenant, [
            'customer_id' => $prefillCustomer['customer_id'] ?? null,
            'customer_name' => $validated['customer_name'],
            'customer_phone' => $validated['customer_phone'],
            'fulfillment_type' => $fulfillmentType,
            'fulfillment_time' => $validated['fulfillment_time'] ?? null,
            'address' => $address,
            'customer_note' => $validated['customer_note'] ?? null,
            'items' => $items,
        ]);

        $request->session()->forget('order_online.prefill_customer');

        $request->session()->put('order_online.latest_order_code', $result['order']->order_code);
        $request->session()->put('order_online.latest_admin_url', $this->onlineOrders->adminWaLink($result['order']));
        $request->session()->put('order_online.latest_customer_url', $this->onlineOrders->customerWaLink($result['order']));

        return redirect()->to('/order/success/'.$result['order']->order_code);
    }

    public function success(Request $request, string $orderCode): View
    {
        $tenant = $this->resolvePublicTenant();
        abort_unless($tenant, 404);

        $order = $this->onlineOrders->findByCode($tenant->id, $orderCode);
        abort_unless($order, 404);

        return view('web.order_online.success', array_merge($this->basePayload(), [
            'order' => $order,
            'orderItems' => $order->items()->orderBy('created_at')->get(),
            'adminWaUrl' => $this->onlineOrders->adminWaLink($order),
            'customerWaUrl' => $this->onlineOrders->customerWaLink($order),
            'clearCartOnLoad' => true,
        ]));
    }

    /**
     * Display the admin PIN verification page
     */
    public function adminPin(Request $request, string $code): View|RedirectResponse
    {
        $order = $this->resolveAdminOrder($request, $code);

        return view('web.order_online.admin_pin', array_merge($this->basePayload(), [
            'order' => $order,
            'orderItems' => $order->items()->orderBy('created_at')->get(),
            'orderCode' => $code,
            'adminVerified' => $request->session()->has('order_online.admin_verified.'.$code),
            'adminOverride' => $this->hasClosedOrderOverride($request, $code),
            'orderLocked' => $this->orderIsClosed($order) && ! $this->hasClosedOrderOverride($request, $code),
        ]));
    }

    public function verifyAdminPin(Request $request, string $code): RedirectResponse
    {
        $order = $this->resolveAdminOrder($request, $code);

        $validated = $request->validate([
            'admin_pin' => ['required', 'string', 'max:6', 'min:4'],
        ]);

        $tenantId = (string) $order->tenant_id;
        try {
            $userId = $this->managerPins->authorizeVoid(
                tenantId: $tenantId,
                actorUserId: $request->user()?->id,
                managerPin: $validated['admin_pin'],
                ipAddress: $request->ip(),
                allowedApproverRoles: ['admin', 'owner', 'superadmin']
            );
        } catch (ManagerPinException $exception) {
            return back()
                ->withErrors(['admin_pin' => $exception->getMessage()])
                ->withInput($request->except('admin_pin'));
        }

        $request->session()->put('order_online.admin_verified.'.$code, $userId);

        return redirect()->to('/admin-order/'.$code);
    }

    public function verifyCompletedOrderOverride(Request $request, string $code): RedirectResponse
    {
        $order = $this->resolveAdminOrder($request, $code);
        $this->assertAdminVerified($request, $code, $order);

        if (! $this->orderIsClosed($order)) {
            return redirect()->to('/admin-order/'.$code)->with('status', 'Order ini belum terkunci.');
        }

        $validated = $request->validate([
            'override_pin' => ['required', 'string', 'max:6', 'min:4'],
            'override_reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $approverId = $this->managerPins->authorizeVoid(
                tenantId: (string) $order->tenant_id,
                actorUserId: $request->user()?->id,
                managerPin: $validated['override_pin'],
                ipAddress: $request->ip(),
                allowedApproverRoles: ['admin', 'owner', 'superadmin']
            );
        } catch (ManagerPinException $exception) {
            return back()
                ->withErrors(['override_pin' => $exception->getMessage()])
                ->withInput($request->except('override_pin'));
        }

        $reason = trim((string) $validated['override_reason']);
        $request->session()->put('order_online.admin_override.'.$code, [
            'approver_id' => $approverId,
            'reason' => $reason,
            'approved_at' => now('UTC')->toISOString(),
        ]);

        $this->auditLogger->log(
            event: 'online_order_closed_override_granted',
            actorId: $approverId,
            referenceType: OnlineOrder::class,
            referenceId: (string) $order->id,
            payload: [
                'order_code' => $order->order_code,
                'status' => $order->status,
                'reason' => $reason,
            ],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            tenantId: (string) $order->tenant_id
        );

        return redirect()->to('/admin-order/'.$code)->with('status', 'Akses khusus aktif untuk order selesai ini.');
    }

    public function adminEdit(Request $request, string $code): View|RedirectResponse
    {
        $order = $this->resolveAdminOrder($request, $code);

        if (! $request->session()->has('order_online.admin_verified.'.$code)) {
            return redirect()->to('/admin-order/'.$code.'/pin');
        }

        if (! $this->canMutateClosedOrder($request, $code, $order)) {
            return redirect()->to('/admin-order/'.$code)
                ->with('error', 'Order selesai terkunci. Buka akses khusus dulu jika benar-benar perlu mengubah data.');
        }

        return view('web.order_online.admin_edit', array_merge($this->basePayload(), [
            'order' => $order,
            'orderItems' => $order->items()->orderBy('created_at')->get(),
            'orderCode' => $code,
        ]));
    }

    public function saveAdminEdit(Request $request, string $code): RedirectResponse
    {
        $order = $this->resolveAdminOrder($request, $code);
        $this->assertAdminVerified($request, $code, $order);
        $this->assertCanMutateClosedOrder($request, $code, $order);

        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:150'],
            'customer_phone' => ['required', 'string', 'max:40'],
            'fulfillment_type' => ['nullable', 'in:pickup,delivery'],
            'fulfillment_time' => ['nullable', 'string', 'max:80'],
            'address' => ['nullable', 'string'],
            'customer_note' => ['nullable', 'string'],
        ]);

        $order->forceFill([
            'customer_name' => trim((string) $validated['customer_name']),
            'customer_phone' => trim((string) $validated['customer_phone']),
            'fulfillment_type' => $validated['fulfillment_type'] ?? 'pickup',
            'fulfillment_time' => trim((string) ($validated['fulfillment_time'] ?? '')) ?: null,
            'address' => trim((string) ($validated['address'] ?? '')) ?: null,
            'customer_note' => trim((string) ($validated['customer_note'] ?? '')) ?: null,
        ])->save();

        $this->onlineOrders->syncCustomer($order->refresh());

        return redirect()->to('/admin-order/'.$code)->with('status', 'Data pesanan tersimpan.');
    }

    /**
     * Display the admin shipping cost page
     */
    public function adminShipping(Request $request, string $code): View|RedirectResponse
    {
        $order = $this->resolveAdminOrder($request, $code);

        if (! $request->session()->has('order_online.admin_verified.'.$code)) {
            return redirect()->to('/admin-order/'.$code.'/pin');
        }

        if (! $this->canMutateClosedOrder($request, $code, $order)) {
            return redirect()->to('/admin-order/'.$code)
                ->with('error', 'Order selesai terkunci. Buka akses khusus dulu jika benar-benar perlu mengubah ongkir.');
        }

        return view('web.order_online.admin_shipping', array_merge($this->basePayload(), [
            'order' => $order,
            'orderItems' => $order->items()->orderBy('created_at')->get(),
            'orderCode' => $code,
        ]));
    }

    public function saveShipping(Request $request, string $code): RedirectResponse
    {
        $order = $this->resolveAdminOrder($request, $code);
        $this->assertAdminVerified($request, $code, $order);
        $this->assertCanMutateClosedOrder($request, $code, $order);

        $validated = $request->validate([
            'shipping_provider' => ['nullable', 'string', 'max:80'],
            'shipping_cost' => ['nullable', 'numeric', 'min:0'],
            'shipping_discount' => ['nullable', 'numeric', 'min:0'],
            'extra_fee' => ['nullable', 'numeric', 'min:0'],
            'customer_note' => ['nullable', 'string'],
        ]);

        $this->onlineOrders->markShippingFilled($order, $validated, $request->user()?->id);

        return redirect()->to('/admin-order/'.$code.'/final-review');
    }

    /**
     * Display the admin final review page
     */
    public function adminFinalReview(Request $request, string $code): View|RedirectResponse
    {
        $order = $this->resolveAdminOrder($request, $code);

        if (! $request->session()->has('order_online.admin_verified.'.$code)) {
            return redirect()->to('/admin-order/'.$code.'/pin');
        }

        return view('web.order_online.admin_final_review', array_merge($this->basePayload(), [
            'order' => $order,
            'orderItems' => $order->items()->orderBy('created_at')->get(),
            'paymentSetting' => $this->onlineOrders->resolvePaymentSetting($order->tenant_id),
            'paymentAccounts' => $this->resolvePaymentAccounts((string) $order->tenant_id),
            'defaultPaymentAccountId' => $this->defaultPaymentAccountId((string) $order->tenant_id),
            'customerWaUrl' => $this->onlineOrders->customerWaLink($order),
            'saleReceiptUrl' => $order->sale_id ? url('/pos/sales/'.$order->sale_id.'/receipt') : null,
            'orderCode' => $code,
            'adminOverride' => $this->hasClosedOrderOverride($request, $code),
            'orderLocked' => $this->orderIsClosed($order) && ! $this->hasClosedOrderOverride($request, $code),
        ]));
    }

    public function sendPaymentMessage(Request $request, string $code): RedirectResponse
    {
        $order = $this->resolveAdminOrder($request, $code);
        $this->assertAdminVerified($request, $code, $order);
        $this->assertCanMutateClosedOrder($request, $code, $order);

        $this->onlineOrders->markPaymentMessageSent($order);
        $customerWaUrl = $this->onlineOrders->customerWaLink($order);

        if ($customerWaUrl) {
            return redirect()->away($customerWaUrl);
        }

        return back()->with('error', 'Nomor WhatsApp pembeli belum valid.');
    }

    public function markPaid(Request $request, string $code): RedirectResponse
    {
        $order = $this->resolveAdminOrder($request, $code);
        $this->assertAdminVerified($request, $code, $order);
        $this->assertCanMutateClosedOrder($request, $code, $order);

        $validated = $request->validate([
            'payment_method' => ['required', 'string', Rule::in(['bank', 'qris', 'cash'])],
            'payment_account_id' => ['required', 'uuid', Rule::exists('chart_of_accounts', 'id')->where('tenant_id', $order->tenant_id)],
        ]);

        $this->onlineOrders->markPaid(
            $order,
            $request->session()->get('order_online.admin_verified.'.$code),
            [
                'method' => $validated['payment_method'],
                'account_id' => $validated['payment_account_id'],
            ]
        );

        return redirect()->to('/admin-order/'.$code.'/final-review')
            ->with('status', 'Pembayaran terverifikasi. Lanjutkan cetak nota atau tandai selesai.');
    }

    private function defaultPaymentAccountId(string $tenantId): ?string
    {
        $mapping = AccountMapping::query()
            ->where('tenant_id', $tenantId)
            ->first(['bank_account_id', 'cash_account_id']);

        return $mapping?->bank_account_id ?: $mapping?->cash_account_id;
    }

    private function resolvePaymentAccounts(string $tenantId): Collection
    {
        $mapping = AccountMapping::query()
            ->where('tenant_id', $tenantId)
            ->first(['cash_account_id', 'bank_account_id']);

        $ids = collect([$mapping?->bank_account_id, $mapping?->cash_account_id])
            ->filter()
            ->values();

        $bankParent = ChartOfAccount::query()
            ->where('tenant_id', $tenantId)
            ->where('code', '1102')
            ->whereNull('parent_id')
            ->first();

        if (! $bankParent) {
            $bankParent = ChartOfAccount::query()
                ->where('tenant_id', $tenantId)
                ->whereNull('parent_id')
                ->where('name', 'like', '%bank%')
                ->orderBy('code')
                ->first();
        }

        if ($bankParent) {
            $ids = $ids
                ->merge([$bankParent->id])
                ->merge(ChartOfAccount::query()
                    ->where('tenant_id', $tenantId)
                    ->where('parent_id', $bankParent->id)
                    ->pluck('id'));
        }

        if ($ids->filter()->isEmpty()) {
            $ids = ChartOfAccount::query()
                ->where('tenant_id', $tenantId)
                ->where('type', 'asset')
                ->where(function ($query) {
                    $query->where('name', 'like', '%bank%')
                        ->orWhere('name', 'like', '%kas%')
                        ->orWhere('name', 'like', '%cash%')
                        ->orWhere('code', 'like', '11%');
                })
                ->orderBy('code')
                ->limit(20)
                ->pluck('id');
        }

        return ChartOfAccount::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $ids->filter()->unique()->values()->all())
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->map(fn (ChartOfAccount $account) => [
                'id' => (string) $account->id,
                'label' => trim(sprintf('%s - %s', $account->code ?: 'Akun', $account->name ?: 'Tanpa Nama'), ' -'),
            ])
            ->values();
    }

    public function cancel(Request $request, string $code): RedirectResponse
    {
        $order = $this->resolveAdminOrder($request, $code);
        $this->assertAdminVerified($request, $code, $order);
        $this->assertCanMutateClosedOrder($request, $code, $order);

        $this->onlineOrders->cancel($order);

        return redirect()->to('/monitor/orders');
    }

    public function complete(Request $request, string $code): RedirectResponse
    {
        $order = $this->resolveAdminOrder($request, $code);
        $this->assertAdminVerified($request, $code, $order);

        abort_unless(
            in_array($order->status, ['pembayaran_terverifikasi', 'diproses', 'siap_diambil', 'siap_dikirim', 'selesai'], true),
            422,
            'Pesanan belum siap ditandai selesai.'
        );

        $this->onlineOrders->complete($order, $request->session()->get('order_online.admin_verified.'.$code));

        return redirect()->to('/admin-order/'.$code.'/final-review')
            ->with('status', 'Pesanan ditandai selesai.');
    }

    /**
     * Display the monitor TV page
     */
    public function monitor(): View
    {
        return view('web.order_online.monitor', array_merge($this->basePayload(), [
            'showMenu' => false,
        ]));
    }

    public function monitorData(Request $request): JsonResponse
    {
        $tenant = $this->resolvePublicTenant();
        abort_unless($tenant, 404);

        $this->stockAvailability->expireStaleReservations($tenant->id);

        $orders = OnlineOrder::query()
            ->with('items')
            ->where('tenant_id', $tenant->id)
            ->orderByDesc('created_at')
            ->get();

        $grouped = $orders->groupBy(fn (OnlineOrder $order) => $this->effectiveOrderStatus($order))
            ->map(fn (Collection $rows) => $rows->map(function (OnlineOrder $order) {
            $status = $this->effectiveOrderStatus($order);

            return [
                'order_code' => $order->order_code,
                'customer_name' => $order->customer_name,
                'customer_phone' => $order->customer_phone,
                'fulfillment_type' => $order->fulfillment_type,
                'fulfillment_time' => $order->fulfillment_time,
                'status' => $status,
                'sale_id' => $order->sale_id ? (string) $order->sale_id : null,
                'total' => (float) $order->grand_total,
                'items' => $order->items->map(fn ($item) => [
                    'product_name' => $item->product_name,
                    'qty' => (int) $item->qty,
                ])->values(),
            ];
        }));

        return response()->json([
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
            ],
            'stats' => [
                'total' => $orders->count(),
                'menunggu_ongkir' => $grouped->get('menunggu_ongkir', collect())->count(),
                'menunggu_pembayaran' => $grouped->get('menunggu_pembayaran', collect())->count(),
                'pembayaran_terverifikasi' => $grouped->get('pembayaran_terverifikasi', collect())->count(),
                'diproses' => $grouped->get('diproses', collect())->count(),
                'siap_diambil' => $grouped->get('siap_diambil', collect())->count(),
                'siap_dikirim' => $grouped->get('siap_dikirim', collect())->count(),
                'selesai' => $grouped->get('selesai', collect())->count(),
            ],
            'orders' => $grouped,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function basePayload(): array
    {
        $tenant = $this->resolvePublicTenant();
        $tenantId = (string) ($tenant?->id ?? '');
        $pickupWindow = $this->pickupWindows->resolve($tenant);
        $pickupLocationLabel = $this->resolvePickupLocationLabel($tenant);

        return [
            'tenantId' => $tenantId !== '' ? $tenantId : null,
            'tenant' => $tenant,
            'tenantName' => $tenant?->name,
            'tenantLogo' => $tenant?->logo_url,
            'tenantSlug' => $tenant?->subdomain ?: ($tenant?->domain ?: ''),
            'settings' => [
                'store_name' => $tenant?->name ?? 'Kala Kasir',
                'pickup_window' => $pickupWindow,
                'pickup_location_label' => $pickupLocationLabel,
            ],
            'pickupWindow' => $pickupWindow,
            'pickupLocationLabel' => $pickupLocationLabel,
            'showMenu' => false,
        ];
    }

    private function resolveCheckoutPrefillCustomer(Request $request): ?array
    {
        $prefillCustomer = $request->session()->get('order_online.prefill_customer');

        if (! is_array($prefillCustomer)) {
            return null;
        }

        $customerId = trim((string) ($prefillCustomer['customer_id'] ?? ''));
        $name = trim((string) ($prefillCustomer['name'] ?? ''));
        $phone = trim((string) ($prefillCustomer['phone'] ?? ''));
        $address = trim((string) ($prefillCustomer['address'] ?? ''));

        if ($customerId === '' || $name === '') {
            return null;
        }

        return [
            'customer_id' => $customerId,
            'name' => $name,
            'phone' => $phone,
            'address' => $address,
        ];
    }

    private function resolvePickupLocationLabel(?Tenant $tenant): string
    {
        if (! $tenant || ! Schema::hasTable('branches')) {
            return 'Lokasi toko';
        }

        $branch = null;
        if ($tenant->default_location_id && Schema::hasTable('locations')) {
            $branchColumns = ['branches.name'];
            if (Schema::hasColumn('branches', 'address')) {
                $branchColumns[] = 'branches.address';
            }

            $branch = DB::table('locations')
                ->join('branches', function ($join) {
                    $join->on('branches.id', '=', 'locations.branch_id')
                        ->on('branches.tenant_id', '=', 'locations.tenant_id');
                })
                ->where('locations.tenant_id', $tenant->id)
                ->where('locations.id', $tenant->default_location_id)
                ->first($branchColumns);
        }

        if (! $branch) {
            $query = DB::table('branches')
                ->where('tenant_id', $tenant->id);

            if (Schema::hasColumn('branches', 'is_active')) {
                $query->orderByDesc('is_active');
            }

            $branch = $query
                ->orderBy('name')
                ->first(array_values(array_filter([
                    'name',
                    Schema::hasColumn('branches', 'address') ? 'address' : null,
                ])));
        }

        $address = trim((string) ($branch->address ?? ''));
        if ($address !== '') {
            return $address;
        }

        $branchName = trim((string) ($branch->name ?? ''));

        return $branchName !== '' ? $branchName : ($tenant->name ?: 'Lokasi toko');
    }

    private function resolvePublicTenant(): ?Tenant
    {
        $candidates = array_filter([
            request()->query('tenant_id'),
            request()->query('tenant'),
            request()->query('tenant_slug'),
            request()->query('store'),
            request()->query('brand'),
            request()->session()->get('order_online.tenant_id'),
            config('app.order_online_tenant_id'),
            config('app.order_online_tenant_name'),
            $this->routeContext->resolveTenantId(),
        ], fn ($value) => filled($value));

        foreach ($candidates as $candidate) {
            $tenant = $this->findTenantCandidate((string) $candidate);
            if ($tenant) {
                request()->session()->put('order_online.tenant_id', $tenant->id);

                return $tenant;
            }
        }

        $leviaTenant = Tenant::query()
            ->where(function ($query) {
                $query->where('name', 'Levia Bakery')
                    ->orWhere('subdomain', 'levia-bakery')
                    ->orWhere('domain', 'levia-bakery');
            })
            ->first();

        if ($leviaTenant) {
            request()->session()->put('order_online.tenant_id', $leviaTenant->id);

            return $leviaTenant;
        }

        $leviaTenant = Tenant::query()
            ->where(function ($query) {
                $query->where('name', 'like', '%Levia%')
                    ->orWhere('subdomain', 'like', '%levia%')
                    ->orWhere('domain', 'like', '%levia%');
            })
            ->orderByRaw("CASE WHEN name = 'Levia Bakery' THEN 0 WHEN name = 'Levia' THEN 1 ELSE 2 END")
            ->first();

        if ($leviaTenant) {
            request()->session()->put('order_online.tenant_id', $leviaTenant->id);

            return $leviaTenant;
        }

        return null;
    }

    private function findTenantCandidate(string $candidate): ?Tenant
    {
        $candidate = trim($candidate);

        if ($candidate === '') {
            return null;
        }

        $tenant = Tenant::query()->find($candidate);
        if ($tenant) {
            return $tenant;
        }

        $normalized = strtolower($candidate);

        return Tenant::query()
            ->whereRaw('lower(name) = ?', [$normalized])
            ->orWhereRaw('lower(subdomain) = ?', [$normalized])
            ->orWhereRaw('lower(domain) = ?', [$normalized])
            ->first();
    }

    private function loadProducts(?Tenant $tenant): Collection
    {
        if (! $tenant || ! Schema::hasTable('products')) {
            return collect();
        }

        $tenantId = $tenant->id;
        $locationId = $this->stockAvailability->resolveDefaultLocationId($tenant);

        $rows = Product::query()
            ->select([
                'products.id',
                'products.name',
                'products.image_url',
                'products.category_id',
                'products.track_stock',
                DB::raw('products.price as selling_price'),
            ])
            ->where('products.tenant_id', $tenantId)
            ->where('products.is_active', true)
            ->where(function ($query) {
                $query->whereNull('products.item_type')
                    ->orWhere('products.item_type', '!=', 'raw_material');
            })
            ->where(function ($query) {
                $query->whereNull('products.product_mode')
                    ->orWhere('products.product_mode', '!=', 'add_on');
            })
            ->orderBy('products.name')
            ->get();

        $productIds = $rows->pluck('id')->all();
        $availability = $locationId ? $this->stockAvailability->availableByProductIds($tenantId, $locationId, $productIds) : [];

        return $rows->map(function ($product) use ($availability) {
            $key = $product->id.'|null';
            $availableQty = $availability[$key] ?? null;

            $product->setAttribute('available_qty', $availableQty);
            $product->setAttribute('stock_state', $availableQty === null ? 'unknown' : ($availableQty <= 0 ? 'sold_out' : ($availableQty <= 3 ? 'low' : 'ready')));

            return $product;
        })->values();
    }

    private function loadCategories(?string $tenantId, Collection $products): Collection
    {
        if (! $tenantId || ! Schema::hasTable('categories')) {
            return collect();
        }

        $categoryIds = $products
            ->pluck('category_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        return Category::query()
            ->when($categoryIds !== [], fn ($query) => $query->whereIn('id', $categoryIds))
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function resolveAdminOrder(Request $request, string $code): OnlineOrder
    {
        $tenant = $this->resolvePublicTenant();
        abort_unless($tenant, 404);

        $order = $this->onlineOrders->findByCode($tenant->id, $code);
        abort_unless($order, 404);

        return $order;
    }

    private function assertAdminVerified(Request $request, string $code, OnlineOrder $order): void
    {
        abort_unless($request->session()->has('order_online.admin_verified.'.$code), 403, 'PIN admin belum diverifikasi.');
    }

    private function orderIsClosed(OnlineOrder $order): bool
    {
        return filled($order->completed_at) || in_array($order->status, ['selesai', 'dibatalkan'], true);
    }

    private function effectiveOrderStatus(OnlineOrder $order): string
    {
        if (filled($order->cancelled_at) || $order->status === 'dibatalkan') {
            return 'dibatalkan';
        }

        if (filled($order->completed_at) || $order->status === 'selesai') {
            return 'selesai';
        }

        return (string) $order->status;
    }

    private function hasClosedOrderOverride(Request $request, string $code): bool
    {
        return $request->session()->has('order_online.admin_override.'.$code);
    }

    private function canMutateClosedOrder(Request $request, string $code, OnlineOrder $order): bool
    {
        return ! $this->orderIsClosed($order) || $this->hasClosedOrderOverride($request, $code);
    }

    private function assertCanMutateClosedOrder(Request $request, string $code, OnlineOrder $order): void
    {
        if ($this->canMutateClosedOrder($request, $code, $order)) {
            return;
        }

        throw ValidationException::withMessages([
            'order_locked' => 'Order selesai terkunci. Buka akses khusus dulu jika benar-benar perlu mengubah data.',
        ]);
    }
}
