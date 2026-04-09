<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OnboardingFlowStateService
{
    /**
     * @param  array{store_name:string,operational_address?:string|null,business_category?:string|null,valuation_method?:string|null}  $payload
     */
    public function storeCompanyProfileStep(Request $request, Tenant $tenant, User $owner, array $payload): void
    {
        $request->session()->put('onboarding.step2', [
            'tenant_id' => $tenant->id,
            'user_id' => $owner->id,
            'store_name' => $payload['store_name'],
            'operational_address' => $payload['operational_address'] ?? null,
            'business_category' => $payload['business_category'] ?? null,
            'valuation_method' => $payload['valuation_method'] ?? 'fifo',
        ]);
    }

    public function storeInventoryMethodStep(Request $request, string $valuationMethod): void
    {
        $request->session()->put('onboarding.step3', [
            'valuation_method' => $valuationMethod,
        ]);
    }

    public function complete(Request $request, ?User $owner): void
    {
        $request->session()->put('onboarding.completed', true);
        $request->session()->forget([
            'onboarding.step1',
            'onboarding.step2',
            'onboarding.step3',
        ]);

        if (! $owner) {
            return;
        }

        Auth::login($owner);
        $request->session()->regenerate();
        $request->session()->put('auth_portal', 'pos');
        $request->session()->put('onboarding.completed', true);
    }
}