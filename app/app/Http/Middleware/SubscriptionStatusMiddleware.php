<?php

namespace App\Http\Middleware;

use App\Services\SubscriptionService;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SubscriptionStatusMiddleware
{
    public function handle(Request $request, Closure $next, ...$allowedStatuses): Response
    {
        $tenant = TenantContext::get();
        if (! $tenant) {
            return response()->json([
                'code' => 'TENANT_NOT_RESOLVED',
                'message' => 'Tenant tidak ditemukan.',
                'details' => [],
            ], 400);
        }

        $subscription = app(SubscriptionService::class);
        if (! $subscription->isStatusAllowed($tenant, $allowedStatuses)) {
            return response()->json([
                'code' => 'SUBSCRIPTION_STATUS_FORBIDDEN',
                'message' => 'Status langganan tidak mengizinkan operasi ini.',
                'details' => [
                    'current' => $subscription->currentStatus($tenant),
                    'allowed' => array_values($allowedStatuses),
                ],
            ], 403);
        }

        return $next($request);
    }
}
