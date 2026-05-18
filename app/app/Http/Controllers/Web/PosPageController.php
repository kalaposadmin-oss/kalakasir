<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AccountMapping;
use App\Models\ChartOfAccount;
use App\Models\Tenant;
use App\Services\ShiftLifecycleService;
use App\Services\WebRouteContextService;
use App\Support\WebEntityFlowContext;
use App\Support\WebSalesFlowContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class PosPageController extends Controller
{
    public function __construct(
        private readonly WebRouteContextService $routeContext,
        private readonly WebSalesFlowContext $salesFlow,
        private readonly WebEntityFlowContext $entityFlow,
    ) {
    }

    public function checkout(Request $request): RedirectResponse|View
    {
        $tenantId = $this->routeContext->resolveTenantId();
        $tenant = $tenantId ? Tenant::query()->find($tenantId) : null;
        $shiftLifecycle = app(ShiftLifecycleService::class);
        $shiftLifecycle->autoCloseExpiredOpenShifts($tenant);
        $pendingReconciliation = $shiftLifecycle->pendingReconciliation($tenant, $request->user()?->id);

        if ($pendingReconciliation) {
            return redirect()->to('/pos/shifts/open?from=%2Fpos%2Fcheckout');
        }

        if (! $this->routeContext->userHasOpenShift($request->user())) {
            return redirect()->to('/pos/shifts/open?from=%2Fpos%2Fcheckout');
        }

        return view('kasir', $this->routeContext->withTenant([
            'showMenu' => true,
        ]));
    }

    public function saleDetail(Request $request, string $sale): View
    {
        return view('sales-detail', array_merge(
            $this->routeContext->withTenant([
                'saleId' => $sale,
                'showMenu' => true,
            ]),
            $this->salesFlow->salesDetailContext($request, 'pos', $sale),
        ));
    }

    public function saleVoidRefund(Request $request, string $sale): View
    {
        return view('void-refund', array_merge(
            $this->routeContext->withTenant([
                'saleId' => $sale,
                'showMenu' => false,
            ]),
            $this->salesFlow->salesVoidRefundContext($request, $sale),
        ));
    }

    public function saleReceipt(Request $request, string $sale): View
    {
        return view('sales-receipt', array_merge(
            $this->routeContext->withTenant([
                'saleId' => $sale,
                'showMenu' => false,
            ]),
            $this->salesFlow->salesReceiptContext($request, $sale),
        ));
    }

    public function paymentMethod(Request $request): View
    {
        return view('payment-method', array_merge([
            'tenantId' => $this->routeContext->resolveTenantId(),
            'bankAccounts' => $this->resolveBankAccounts(),
            'showMenu' => false,
        ], $this->salesFlow->paymentMethodContext($request)));
    }

    public function receiptPreview(Request $request): View
    {
        return view('sales-receipt', array_merge(
            $this->routeContext->withTenant([
                'showMenu' => false,
            ]),
            $this->salesFlow->salesReceiptContext($request, trim((string) $request->query('sale_id', ''))),
        ));
    }

    public function shiftSummaryIndex(Request $request): RedirectResponse|View
    {
        if ($request->filled('shift_id')) {
            $query = $request->query();
            $shiftId = trim((string) ($query['shift_id'] ?? ''));
            unset($query['shift_id']);

            return redirect()->to('/pos/shifts/'.$shiftId.'/summary'.($query ? '?'.http_build_query($query) : ''));
        }

        return view('shift-close-summary', $this->routeContext->withTenant([
            'showMenu' => true,
            'currentPath' => '/pos/shifts/summary',
            'backUrl' => pos_default_url($request->user()),
            'shiftId' => null,
        ]));
    }

    public function shiftSummaryDetail(Request $request, string $shift): View
    {
        return view('shift-close-summary', array_merge(
            $this->routeContext->withTenant([
                'showMenu' => true,
            ]),
            $this->entityFlow->detailContext(
                $request,
                'pos',
                '/pos/shifts/'.$shift.'/summary',
                '/pos/shifts/summary',
                ['/pos/shifts/summary', '/pos/dashboard', '/pos/checkout'],
                ['shiftId' => $shift],
            ),
        ));
    }

    public function productDetail(Request $request, string $product): View
    {
        return view('product-detail', array_merge(
            $this->routeContext->withTenant([
                'showMenu' => true,
                'productId' => $product,
            ]),
            $this->entityFlow->detailContext(
                $request,
                'pos',
                '/pos/products/'.$product,
                '/pos/products',
                ['/pos/products', '/pos/dashboard', '/pos/checkout'],
                ['productId' => $product],
            ),
        ));
    }

    private function resolveBankAccounts(): Collection
    {
        $bankParent = ChartOfAccount::query()
            ->where('code', '1102')
            ->whereNull('parent_id')
            ->first();

        if (! $bankParent) {
            $bankParent = ChartOfAccount::query()
                ->whereNull('parent_id')
                ->where('name', 'like', '%bank%')
                ->orderBy('code')
                ->first();
        }

        if (! $bankParent) {
            $mappedId = AccountMapping::query()->value('bank_account_id');
            if ($mappedId) {
                $mapped = ChartOfAccount::query()->find($mappedId);
                if ($mapped) {
                    $bankParent = $mapped->parent_id
                        ? ChartOfAccount::query()->find($mapped->parent_id)
                        : $mapped;
                }
            }
        }

        if (! $bankParent) {
            return collect();
        }

        $bankAccounts = ChartOfAccount::query()
            ->where('parent_id', $bankParent->id)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'parent_id'])
            ->filter(fn ($account) => preg_match('/^11\d+$/', (string) ($account->code ?? '')));

        if ($bankAccounts->isEmpty() && preg_match('/^11\d+$/', (string) ($bankParent->code ?? ''))) {
            return collect([$bankParent]);
        }

        return $bankAccounts;
    }
}
