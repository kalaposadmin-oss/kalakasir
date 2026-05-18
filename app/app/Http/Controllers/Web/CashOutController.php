<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\CashTransactionService;
use App\Services\WebRouteContextService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CashOutController extends Controller
{
    public function __construct(
        private readonly WebRouteContextService $routeContext,
        private readonly CashTransactionService $cashTransactionService,
    ) {
    }

    public function index(Request $request): View
    {
        return $this->renderPage($request, false);
    }

    public function create(Request $request): View
    {
        return $this->renderPage($request, true);
    }

    public function store(Request $request)
    {
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
                'cash-out',
                $validated,
                $request->user(),
                $request->ip(),
                $request->userAgent(),
            );
        } catch (ValidationException $exception) {
            return redirect('/pos/cash-out')->withErrors($exception->errors())->withInput();
        }

        return redirect('/pos/cash-out')->with('status', 'Kas keluar berhasil dicatat.');
    }

    private function renderPage(Request $request, bool $focusForm): View
    {
        $tenantId = (string) $this->routeContext->resolveTenantId();

        return view('cash-transaction-form', array_merge(
            $this->routeContext->withTenant([
                'showMenu' => true,
                'transactionDirection' => 'cash-out',
                'transactionTitle' => 'Kas Keluar',
                'transactionSubtitle' => 'Catat pengeluaran kas langsung ke jurnal dan saldo akun.',
                'transactionActionLabel' => 'Simpan Kas Keluar',
                'transactionModeLabel' => 'Pengeluaran kas',
                'submitUrl' => '/pos/cash-out',
                'alternateUrl' => '/pos/cash-in/create',
                'focusForm' => $focusForm,
            ]),
            $this->cashTransactionService->formContext($tenantId, 'cash-out'),
        ));
    }
}