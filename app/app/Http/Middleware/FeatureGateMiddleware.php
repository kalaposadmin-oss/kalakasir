<?php

namespace App\Http\Middleware;

use App\Services\SubscriptionService;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FeatureGateMiddleware
{
    public function handle(Request $request, Closure $next, ...$features): Response
    {
        $tenant = TenantContext::get();
        if (! $tenant) {
            return response()->json([
                'code' => 'TENANT_NOT_RESOLVED',
                'message' => 'Tenant tidak ditemukan.',
                'details' => [],
            ], 400);
        }

        $featureKey = (string) ($features[0] ?? '');
        if ($featureKey === '') {
            return $next($request);
        }

        $subscription = app(SubscriptionService::class);
        if (! $subscription->canAccessFeature($tenant, $featureKey)) {
            return response()->json([
                'code' => 'FEATURE_NOT_ALLOWED',
                'message' => 'Fitur tidak tersedia untuk paket/status langganan saat ini.',
                'details' => [
                    'feature' => $featureKey,
                    'plan' => $subscription->currentPlan($tenant),
                    'status' => $subscription->currentStatus($tenant),
                ],
            ], 403);
        }

        return $next($request);
    }
}
