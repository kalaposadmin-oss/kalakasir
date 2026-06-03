<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\CashTransactionService;
use App\Services\WebRouteContextService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CashManagementController extends Controller
{
    public function __construct(
        private readonly WebRouteContextService $routeContext,
        private readonly CashTransactionService $cashTransactionService,
    ) {
    }

    public function index(Request $request): View
    {
        $tenantId = (string) $this->routeContext->resolveTenantId();
        $month = $request->query('month', now()->format('Y-m'));
        $isBackoffice = $request->is('backoffice/*');
        $basePath = $isBackoffice ? '/backoffice/finance/cash' : '/pos/cash-in';

        return view('cash-management', array_merge(
            $this->routeContext->withTenant([
                'showMenu' => true,
                'cashPortal' => $isBackoffice ? 'backoffice' : 'pos',
                'cashBasePath' => $basePath,
                'cashInUrl' => $basePath,
                'cashOutUrl' => $isBackoffice ? '/backoffice/finance/cash/out' : '/pos/cash-out',
                'cashBackUrl' => $isBackoffice ? '/backoffice/finance/summary' : pos_default_url(),
            ]),
            $this->cashTransactionService->managementContext($tenantId, $month),
        ));
    }

    public function storeCashIn(Request $request)
    {
        return $this->storeTransaction($request, 'cash-in');
    }

    public function storeCashOut(Request $request)
    {
        return $this->storeTransaction($request, 'cash-out');
    }

    private function storeTransaction(Request $request, string $direction)
    {
        $isBackoffice = $request->is('backoffice/*');
        $redirectUrl = $isBackoffice ? '/backoffice/finance/cash' : '/pos/cash-in';

        $validated = $request->validate([
            'transaction_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'account_id' => ['required', 'uuid'],
            'category' => ['required', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:500'],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            $this->cashTransactionService->store(
                (string) $this->routeContext->resolveTenantId(),
                $direction,
                $validated,
                $request->user(),
                $request->ip(),
                $request->userAgent(),
            );
        } catch (ValidationException $exception) {
            return redirect($redirectUrl)
                ->withErrors($exception->errors())
                ->withInput(array_merge($request->all(), ['direction' => $direction]));
        }

        $label = $direction === 'cash-in' ? 'Kas masuk' : 'Kas keluar';

        return redirect($redirectUrl)->with('status', "$label berhasil dicatat.");
    }
}
