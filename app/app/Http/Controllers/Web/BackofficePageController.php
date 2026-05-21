<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AccountMapping;
use App\Models\ChartOfAccount;
use App\Models\OnlineOrder;
use App\Models\PaymentSetting;
use App\Models\Tenant;
use App\Services\OrderPickupWindowService;
use App\Services\TenantDefaultAccountingSetupService;
use App\Services\WebRouteContextService;
use App\Support\WebEntityFlowContext;
use App\Support\WebSalesFlowContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BackofficePageController extends Controller
{
    public function __construct(
        private readonly WebRouteContextService $routeContext,
        private readonly WebSalesFlowContext $salesFlow,
        private readonly WebEntityFlowContext $entityFlow,
        private readonly OrderPickupWindowService $pickupWindows,
    ) {
    }

    public function saleDetail(Request $request, string $sale): View
    {
        return view('sales-detail', array_merge(
            $this->routeContext->withTenant([
                'saleId' => $sale,
                'showMenu' => true,
            ]),
            $this->salesFlow->salesDetailContext($request, 'backoffice', $sale),
        ));
    }

    public function onlineOrderDetail(Request $request, string $order): RedirectResponse
    {
        $onlineOrder = $this->resolveOnlineOrder($order);

        return redirect()->to('/admin-order/'.$onlineOrder->order_code.'?tenant_id='.$onlineOrder->tenant_id);
    }

    public function onlineOrderProcess(Request $request, string $order): RedirectResponse
    {
        $onlineOrder = $this->resolveOnlineOrder($order);
        $target = in_array($onlineOrder->status, ['menunggu_ongkir'], true)
            ? '/admin-order/'.$onlineOrder->order_code.'/shipping'
            : '/admin-order/'.$onlineOrder->order_code.'/final-review';

        return redirect()->to($target.'?tenant_id='.$onlineOrder->tenant_id);
    }

    public function productDetail(Request $request, string $product): View
    {
        return $this->renderDetailView(
            $request,
            'product-detail',
            '/backoffice/products/'.$product,
            '/backoffice/products',
            ['/backoffice/products', '/backoffice/inventory', '/backoffice/reports/inventory'],
            ['productId' => $product],
        );
    }

    public function branchEdit(Request $request, string $branch): View
    {
        return $this->renderDetailView(
            $request,
            'branch-edit',
            '/backoffice/branches/'.$branch.'/edit',
            '/backoffice/branches',
            ['/backoffice/branches', '/backoffice/dashboard'],
            ['branchId' => $branch],
        );
    }

    public function purchaseDetail(Request $request, string $purchase): View
    {
        return $this->renderDetailView(
            $request,
            'purchase-detail',
            '/backoffice/purchases/'.$purchase,
            '/backoffice/purchases',
            ['/backoffice/purchases', '/backoffice/purchases/detail', '/backoffice/receiving'],
            ['purchaseId' => $purchase],
        );
    }

    public function receivingIndex(Request $request): RedirectResponse|View
    {
        if ($request->filled('purchase_id')) {
            $query = $request->query();
            $purchaseId = trim((string) ($query['purchase_id'] ?? ''));
            unset($query['purchase_id']);

            return redirect()->to('/backoffice/receiving/'.$purchaseId.($query ? '?'.http_build_query($query) : ''));
        }

        return view('receiving', $this->routeContext->withTenant([
            'showMenu' => true,
            'currentPath' => '/backoffice/receiving',
            'backUrl' => '/backoffice/purchases',
            'purchaseId' => null,
        ]));
    }

    public function receivingActionRedirect(Request $request): RedirectResponse
    {
        return redirect()->to('/backoffice/receiving'.($request->getQueryString() ? '?'.$request->getQueryString() : ''));
    }

    public function receivingDetail(Request $request, string $purchase): View
    {
        return $this->renderDetailView(
            $request,
            'receiving',
            '/backoffice/receiving/'.$purchase,
            '/backoffice/receiving',
            ['/backoffice/receiving', '/backoffice/purchases', '/backoffice/purchases/detail'],
            ['purchaseId' => $purchase],
        );
    }

    public function receivingAction(Request $request, string $purchase): View
    {
        return $this->renderDetailView(
            $request,
            'receiving-action',
            '/backoffice/receiving/'.$purchase.'/action',
            '/backoffice/receiving',
            ['/backoffice/receiving', '/backoffice/purchases', '/backoffice/purchases/detail'],
            ['purchaseId' => $purchase],
        );
    }

    public function stockCardDetail(Request $request, string $product): View
    {
        return $this->renderDetailView(
            $request,
            'stock-card',
            '/backoffice/inventory/products/'.$product.'/stock-card',
            '/backoffice/reports/inventory',
            ['/backoffice/reports/inventory', '/backoffice/inventory', '/backoffice/products', '/backoffice/products/detail'],
            ['productId' => $product],
        );
    }

    public function productionBatchDetail(Request $request, string $batch): View
    {
        return $this->renderDetailView(
            $request,
            'production-batch-detail',
            '/backoffice/inventory/production-batches/'.$batch,
            '/backoffice/inventory/production-batches',
            ['/backoffice/inventory/production-batches', '/backoffice/inventory'],
            ['batchId' => $batch],
        );
    }

    public function paymentSettings(Request $request): View
    {
        $tenantId = $this->routeContext->resolveTenantId();
        if ($tenantId) {
            app(TenantDefaultAccountingSetupService::class)->ensure($tenantId, $request->user()?->id);
        }

        $paymentSetting = $tenantId
            ? PaymentSetting::query()
                ->where('tenant_id', $tenantId)
                ->orderByDesc('created_at')
                ->first()
            : null;
        $accountMapping = $tenantId
            ? AccountMapping::query()->where('tenant_id', $tenantId)->first()
            : null;
        $paymentAccounts = $tenantId
            ? ChartOfAccount::query()
                ->where('tenant_id', $tenantId)
                ->where('type', 'asset')
                ->where(function ($query): void {
                    $query->where('code', 'like', '11%')
                        ->orWhere('name', 'like', '%kas%')
                        ->orWhere('name', 'like', '%cash%')
                        ->orWhere('name', 'like', '%bank%')
                        ->orWhere('name', 'like', '%qris%');
                })
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'type', 'parent_id', 'bank_name', 'bank_account_number', 'bank_account_name'])
            : collect();

        return view('payment-settings', $this->routeContext->withTenant([
            'showMenu' => true,
            'currentPath' => '/backoffice/settings/payment-settings',
            'backUrl' => '/backoffice/dashboard',
            'paymentSetting' => $paymentSetting,
            'accountMapping' => $accountMapping,
            'paymentAccounts' => $paymentAccounts,
        ]));
    }

    public function savePaymentSettings(Request $request): RedirectResponse
    {
        $tenantId = $this->routeContext->resolveTenantId();
        abort_unless($tenantId, 404);

        app(TenantDefaultAccountingSetupService::class)->ensure($tenantId, $request->user()?->id);

        $validated = $request->validate([
            'cash_account_id' => ['required', 'uuid', Rule::exists('chart_of_accounts', 'id')->where('tenant_id', $tenantId)->where('type', 'asset')],
            'bank_account_id' => ['required', 'uuid', Rule::exists('chart_of_accounts', 'id')->where('tenant_id', $tenantId)->where('type', 'asset')],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'bank_account_number' => ['nullable', 'string', 'max:120'],
            'bank_account_name' => ['nullable', 'string', 'max:150'],
            'admin_whatsapp_number' => ['nullable', 'string', 'max:30'],
            'qris_image_url' => ['nullable', 'url', 'max:2048'],
            'manager_pin' => ['nullable', 'string', 'min:4', 'max:6'],
            'payment_note' => ['nullable', 'string'],
            'is_active' => ['nullable'],
        ]);

        if ((string) $validated['cash_account_id'] === (string) $validated['bank_account_id']) {
            throw ValidationException::withMessages([
                'bank_account_id' => 'Akun bank/QRIS harus berbeda dari akun kas tunai.',
            ]);
        }

        $selectedBankAccount = ChartOfAccount::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $validated['bank_account_id'])
            ->first();

        $accountMapping = AccountMapping::query()->where('tenant_id', $tenantId)->first();
        if ($accountMapping) {
            $accountMapping->forceFill([
                'cash_account_id' => $validated['cash_account_id'],
                'bank_account_id' => $validated['bank_account_id'],
                'updated_by' => $request->user()?->id ?: $accountMapping->updated_by,
            ])->save();
        }

        $paymentSetting = PaymentSetting::query()
            ->where('tenant_id', $tenantId)
            ->orderByDesc('created_at')
            ->first() ?? new PaymentSetting();

        if (! $paymentSetting->exists) {
            $paymentSetting->forceFill([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
            ]);
        }

        $paymentSetting->forceFill([
            'bank_name' => trim((string) ($validated['bank_name'] ?? '')) ?: ($selectedBankAccount?->bank_name ?: $selectedBankAccount?->name),
            'bank_account_number' => trim((string) ($validated['bank_account_number'] ?? '')) ?: ($selectedBankAccount?->bank_account_number ?: null),
            'bank_account_name' => trim((string) ($validated['bank_account_name'] ?? '')) ?: ($selectedBankAccount?->bank_account_name ?: null),
            'admin_whatsapp_number' => trim((string) ($validated['admin_whatsapp_number'] ?? '')) ?: null,
            'qris_image_url' => trim((string) ($validated['qris_image_url'] ?? '')) ?: null,
            'payment_note' => trim((string) ($validated['payment_note'] ?? '')) ?: null,
            'is_active' => $request->boolean('is_active', true),
        ])->save();

        $managerPin = trim((string) ($validated['manager_pin'] ?? ''));
        if ($managerPin !== '' && $request->user()) {
            $request->user()->forceFill([
                'manager_pin_hash' => Hash::make($managerPin),
            ])->save();
        }

        return redirect()->back()->with('status', $managerPin !== '' ? 'Pengaturan pembayaran dan PIN admin tersimpan.' : 'Pengaturan pembayaran tersimpan.');
    }

    public function savePreferences(Request $request): RedirectResponse
    {
        $tenantId = $this->routeContext->resolveTenantId();
        abort_unless($tenantId, 404);

        $validated = $request->validate([
            'pickup_window_enabled' => ['nullable', 'boolean'],
            'pickup_start_time' => ['required_if:pickup_window_enabled,1', 'nullable', 'date_format:H:i'],
            'pickup_end_time' => ['required_if:pickup_window_enabled,1', 'nullable', 'date_format:H:i'],
        ]);

        $tenant = Tenant::query()->find($tenantId);
        $enabled = $request->boolean('pickup_window_enabled');
        $startTime = (string) ($validated['pickup_start_time'] ?? '11:00');
        $endTime = (string) ($validated['pickup_end_time'] ?? '17:00');

        if ($enabled && $startTime >= $endTime) {
            throw ValidationException::withMessages([
                'pickup_end_time' => 'Jam selesai harus lebih besar dari jam mulai.',
            ]);
        }

        $this->pickupWindows->save($tenantId, [
            'enabled' => $enabled,
            'start_time' => $startTime,
            'end_time' => $endTime,
        ], $tenant?->timezone);

        return redirect()->back()->with('status', 'Jadwal pengambilan order online tersimpan.');
    }

    private function renderDetailView(
        Request $request,
        string $view,
        string $currentPath,
        string $fallbackBackUrl,
        array $allowedBackPrefixes,
        array $extra,
    ): View {
        return view($view, array_merge(
            $this->routeContext->withTenant(array_merge($extra, [
                'showMenu' => true,
            ])),
            $this->entityFlow->detailContext(
                $request,
                'backoffice',
                $currentPath,
                $fallbackBackUrl,
                $allowedBackPrefixes,
                $extra,
            ),
        ));
    }

    private function resolveOnlineOrder(string $order): OnlineOrder
    {
        $tenantId = $this->routeContext->resolveTenantId();
        abort_unless($tenantId, 404);

        $onlineOrder = OnlineOrder::query()
            ->where('tenant_id', $tenantId)
            ->where(function ($query) use ($order) {
                $query->where('id', $order)
                    ->orWhere('order_code', $order);
            })
            ->first();

        abort_unless($onlineOrder, 404);

        return $onlineOrder;
    }
}
