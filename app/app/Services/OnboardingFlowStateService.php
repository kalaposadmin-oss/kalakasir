<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OnboardingFlowStateService
{
    /**
     * @param  array{name:string,email:string,phone?:string|null,password:string}  $payload
     */
    public function storeAccountStep(Request $request, array $payload): void
    {
        $request->session()->put('onboarding.step1', [
            'name' => $payload['name'],
            'email' => strtolower($payload['email']),
            'phone' => $payload['phone'] ?? null,
            'password' => $payload['password'],
        ]);
    }

    /**
     * @param  array{store_name:string,operational_address?:string|null,business_category?:string|null,valuation_method?:string|null,timezone?:string|null}  $payload
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
            'timezone' => $payload['timezone'] ?? 'Asia/Jakarta',
        ]);
    }

    public function storeInventoryMethodStep(Request $request, string $valuationMethod): void
    {
        $request->session()->put('onboarding.step3', [
            'valuation_method' => $valuationMethod,
        ]);

        $stepTwo = (array) $request->session()->get('onboarding.step2', []);
        if ($stepTwo !== []) {
            $stepTwo['valuation_method'] = $valuationMethod;
            $request->session()->put('onboarding.step2', $stepTwo);
        }
    }

    /**
     * @param  array{name?:string,sku?:string|null,category?:string|null,hpp_average?:numeric-string|int|float|null,price?:numeric-string|int|float|null,initial_stock?:numeric-string|int|float|null,variants?:array<int,array{id?:string|null,sku?:string,name?:string}>,initial_stock_lines?:array<int,array{product_variant_id?:string|null,qty?:numeric-string|int|float|null,unit_cost?:numeric-string|int|float|null}>}  $payload
     */
    public function storeInitialProductStep(Request $request, array $payload, bool $skipProduct): void
    {
        $request->session()->put('onboarding.step4', [
            'skip_product' => $skipProduct,
            'product' => [
                'name' => $payload['name'] ?? null,
                'sku' => $payload['sku'] ?? null,
                'category' => $payload['category'] ?? null,
                'hpp_average' => $payload['hpp_average'] ?? null,
                'price' => $payload['price'] ?? null,
                'initial_stock' => $payload['initial_stock'] ?? null,
                'variants' => array_values($payload['variants'] ?? []),
                'initial_stock_lines' => array_values($payload['initial_stock_lines'] ?? []),
            ],
        ]);
    }

    public function complete(Request $request, ?User $owner): void
    {
        $request->session()->put('onboarding.completed', true);
        $request->session()->forget([
            'onboarding.step1',
            'onboarding.step2',
            'onboarding.step3',
            'onboarding.step4',
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