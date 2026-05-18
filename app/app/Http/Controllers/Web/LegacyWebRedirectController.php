<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\LegacyWebRedirectService;
use App\Support\WebSalesFlowContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LegacyWebRedirectController extends Controller
{
    public function __construct(
        private readonly WebSalesFlowContext $salesFlow,
        private readonly LegacyWebRedirectService $redirects,
    ) {
    }

    public function to(Request $request): RedirectResponse
    {
        return $this->redirects->to($this->interpolateTarget((string) $request->route('target', ''), $request), $request);
    }

    public function entityOrFallback(Request $request): RedirectResponse
    {
        $queryKey = (string) $request->route('queryKey', '');
        $detailPrefix = (string) $request->route('detailPrefix', '');
        $detailSuffix = (string) $request->route('detailSuffix', '');
        $fallbackTarget = (string) $request->route('fallbackTarget', '');

        abort_unless($queryKey !== '' && $fallbackTarget !== '', 404);

        return $this->redirects->entityOrFallback(
            $request,
            $queryKey,
            fn (string $entityId) => $detailPrefix.$entityId.$detailSuffix,
            $fallbackTarget,
        );
    }

    public function stockCard(Request $request): RedirectResponse
    {
        $portalPrefix = session('auth_portal', 'pos') === 'backoffice' ? '/backoffice' : '/pos';
        $query = $request->query();
        $productId = trim((string) ($query['product_id'] ?? ''));
        unset($query['product_id']);

        $target = $productId !== ''
            ? $portalPrefix.'/inventory/products/'.$productId.'/stock-card'
            : ($portalPrefix === '/backoffice' ? '/backoffice/reports/inventory/stock-card' : '/pos/inventory/stock-card');

        return redirect()->to($this->appendQuery($target, $query));
    }

    public function salesDetail(Request $request): RedirectResponse
    {
        $saleId = trim((string) $request->query('sale_id', ''));
        abort_if($saleId === '', 404);

        $query = $request->query();
        unset($query['sale_id']);

        return redirect()->to($this->appendQuery(
            $this->salesFlow->legacySalesDetailTarget($request, $saleId),
            $query,
        ));
    }

    public function salesHistory(Request $request): RedirectResponse
    {
        $target = session('auth_portal', 'pos') === 'backoffice'
            ? '/backoffice/reports/sales'
            : '/pos/history';

        return redirect()->to($this->appendRawQuery($target, $request->getQueryString()));
    }

    public function salesVoidRefund(Request $request): RedirectResponse
    {
        $query = $request->query();
        $saleId = trim((string) ($query['sale_id'] ?? ''));
        unset($query['sale_id']);

        return redirect()->to($this->appendQuery(
            $this->salesFlow->legacySalesVoidRefundTarget($request, $saleId),
            $query,
        ));
    }

    public function receiptPreview(Request $request): RedirectResponse
    {
        $query = $request->query();
        $saleId = trim((string) ($query['sale_id'] ?? ''));
        unset($query['sale_id']);

        return redirect()->to($this->appendQuery(
            $this->salesFlow->legacyReceiptPreviewTarget($saleId),
            $query,
        ));
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function appendQuery(string $target, array $query): string
    {
        return $query !== [] ? $target.'?'.http_build_query($query) : $target;
    }

    private function appendRawQuery(string $target, ?string $query): string
    {
        return $query ? $target.'?'.$query : $target;
    }

    private function interpolateTarget(string $target, Request $request): string
    {
        $parameters = $request->route()?->parameters() ?? [];

        foreach ($parameters as $key => $value) {
            $target = str_replace('{'.$key.'}', (string) $value, $target);
        }

        return $target;
    }
}