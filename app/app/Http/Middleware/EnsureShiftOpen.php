<?php

namespace App\Http\Middleware;

use App\Models\Shift;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureShiftOpen
{
    /**
     * Ensure the authenticated user has an active (open) shift before
     * accessing POS transaction pages like checkout and payment.
     *
     * If no open shift is found the user is redirected to the POS shift
     * opening screen while preserving the attempted transaction page.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user) {
            return redirect('/');
        }

        $tenantId = TenantContext::id() ?: $user->tenant_id;

        if (! $tenantId) {
            return redirect('/pos/dashboard');
        }

        $hasOpenShift = Shift::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $user->getAuthIdentifier())
            ->where('status', 'open')
            ->exists();

        if (! $hasOpenShift) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Shift belum dibuka. Buka shift terlebih dahulu.',
                    'redirect' => '/pos/dashboard',
                ], 403);
            }

            $target = '/pos/shifts/open?from='.rawurlencode('/'.$request->path());

            return redirect($target)
                ->with('shift_required', true);
        }

        return $next($request);
    }

    /**
     * Check whether a given user currently has an open shift.
     *
     * This static helper can be called from controllers or other services
     * without needing to go through the middleware pipeline.
     */
    public static function hasOpenShift(?string $tenantId, ?string $userId): bool
    {
        if (! $tenantId || ! $userId) {
            return false;
        }

        return Shift::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('status', 'open')
            ->exists();
    }
}
