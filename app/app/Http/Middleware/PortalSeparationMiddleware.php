<?php

namespace App\Http\Middleware;

use App\Support\WebPortalResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class PortalSeparationMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $requiredPortal = $this->resolveRequiredPortal($request);

        if ($requiredPortal === null) {
            return $next($request);
        }

        if (! Auth::check()) {
            return redirect($requiredPortal === 'backoffice' ? '/backoffice/login' : '/pos/login');
        }

        $activePortal = $this->resolveActivePortal($request, $requiredPortal);
        if ($activePortal !== $requiredPortal) {
            return redirect($activePortal === 'backoffice' ? '/backoffice/dashboard' : '/pos/dashboard');
        }

        return $next($request);
    }

    private function resolveRequiredPortal(Request $request): ?string
    {
        $path = trim($request->path(), '/');
        if ($path === '') {
            return null;
        }

        // Shared reporting pages are linked from backoffice menu and also reused in POS flows.
        if ($request->is('sales-history*')
            || $request->is('sales-detail*')
            || $request->is('sales-void-refund*')
            || $request->is('shift/open*')
            || $request->is('shift/close*')
            || $request->is('shift/close-summary*')
            || $request->is('menu*')
            || $request->is('menu/settings*')) {
            return null;
        }

        // Auth entry points must stay publicly accessible to avoid self-redirect loops.
        if ($request->is('login')
            || $request->is('pos/login')
            || $request->is('backoffice/login')
            || $request->is('register*')
            || $request->is('logout')) {
            return null;
        }

        if ($this->matchesBackoffice($request)) {
            return 'backoffice';
        }

        if ($this->matchesPos($request)) {
            return 'pos';
        }

        return null;
    }

    private function matchesBackoffice(Request $request): bool
    {
        return $request->is('backoffice/*')
            || $request->is('users*')
            || $request->is('branches*')
            || $request->is('settings/*')
            || $request->is('analytics*')
            || $request->is('reports/*')
            || $request->is('inventory')
            || $request->is('stock-card*')
            || $request->is('products*')
            || $request->is('categories*')
            || $request->is('materials*')
            || $request->is('customers*')
            || $request->is('suppliers*')
            || $request->is('purchases*')
            || $request->is('receiving*')
            || $request->is('purchase-returns*')
            || $request->is('stock-alerts*')
            || $request->is('stock-adjustment*')
            || $request->is('finance-summary*')
            || $request->is('lock-periods*')
            || $request->is('stock-transfers*')
            || $request->is('loyalty*')
            || $request->is('menu*');
    }

    private function matchesPos(Request $request): bool
    {
        return $request->is('pos/*')
            || $request->is('kasir*')
            || $request->is('sales-history*')
            || $request->is('sales-detail*')
            || $request->is('sales-void-refund*')
            || $request->is('payment-method*')
            || $request->is('receipt-preview*')
            || $request->is('shift/*');
    }

    private function resolveActivePortal(Request $request, string $requiredPortal): string
    {
        $fallbackPortal = in_array($requiredPortal, ['pos', 'backoffice'], true) ? $requiredPortal : 'pos';

        return app(WebPortalResolver::class)->resolveActivePortal($request, $fallbackPortal);
    }
}
