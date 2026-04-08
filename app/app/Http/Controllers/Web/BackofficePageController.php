<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\WebRouteContextService;
use App\Support\WebEntityFlowContext;
use App\Support\WebSalesFlowContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BackofficePageController extends Controller
{
    public function __construct(
        private readonly WebRouteContextService $routeContext,
        private readonly WebSalesFlowContext $salesFlow,
        private readonly WebEntityFlowContext $entityFlow,
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
            '/backoffice/suppliers',
            ['/backoffice/suppliers', '/backoffice/purchases/detail', '/backoffice/purchases/', '/backoffice/receiving'],
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
            'backUrl' => '/backoffice/suppliers',
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
            ['/backoffice/receiving', '/backoffice/purchases', '/backoffice/purchases/detail', '/backoffice/suppliers'],
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
            ['/backoffice/receiving', '/backoffice/receiving/', '/backoffice/purchases', '/backoffice/purchases/detail', '/backoffice/suppliers'],
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
}