<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Services\OnboardingFlowStateService;
use App\Services\OnboardingInitialProductService;
use App\Services\OnboardingProvisioningService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class OnboardingController extends Controller
{
    public function rootRedirect(): RedirectResponse
    {
        $tenantId = $this->resolveTenantId();

        if ($tenantId && Schema::hasTable('users')) {
            $hasUsers = DB::table('users')
                ->where('tenant_id', $tenantId)
                ->exists();

            if (! $hasUsers) {
                return redirect('/register/step-1');
            }
        }

        return redirect('/login');
    }

    public function registerRedirect(): RedirectResponse
    {
        return redirect('/register/step-1');
    }

    public function showStepOne(Request $request): RedirectResponse|View
    {
        if ($request->session()->has('onboarding.completed')) {
            return redirect('/');
        }

        return view('onboarding-register-user', [
            'tenantId' => $this->resolveTenantId(),
            'showMenu' => false,
        ]);
    }

    public function storeStepOne(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'accept_terms' => ['accepted'],
        ]);

        $request->session()->put('onboarding.step1', [
            'name' => $validated['name'],
            'email' => strtolower($validated['email']),
            'phone' => $validated['phone'] ?? null,
            'password' => $validated['password'],
        ]);

        return redirect('/register/step-2');
    }

    public function showStepTwo(Request $request): RedirectResponse|View
    {
        if (! $request->session()->has('onboarding.step1')) {
            return redirect('/register/step-1');
        }

        return view('onboarding-company-profile', [
            'tenantId' => $this->resolveTenantId(),
            'showMenu' => false,
        ]);
    }

    public function storeStepTwo(Request $request, OnboardingProvisioningService $provisioning, OnboardingFlowStateService $flowState): RedirectResponse
    {
        if (! $request->session()->has('onboarding.step1')) {
            return redirect('/register/step-1');
        }

        $validated = $request->validate([
            'store_name' => ['required', 'string', 'max:190'],
            'operational_address' => ['nullable', 'string', 'max:500'],
            'business_category' => ['nullable', 'in:fnb,retail,service,other'],
            'valuation_method' => ['nullable', 'in:fifo,average'],
            'timezone' => ['nullable', 'string', 'max:64'],
        ]);

        $timezone = $validated['timezone'] ?? 'Asia/Jakarta';
        $userData = $request->session()->get('onboarding.step1');
        $existingTenantId = (string) $request->session()->get('onboarding.step2.tenant_id', '');

        [$tenant, $owner] = $provisioning->completeCompanyProfileStep(
            userData: $userData,
            companyData: array_merge($validated, ['timezone' => $timezone]),
            existingTenantId: $existingTenantId !== '' ? $existingTenantId : null,
        );

        $flowState->storeCompanyProfileStep($request, $tenant, $owner, $validated);

        return redirect('/register/step-3');
    }

    public function showStepThree(Request $request): RedirectResponse|View
    {
        if (! $request->session()->has('onboarding.step2')) {
            return redirect('/register/step-2');
        }

        return view('onboarding-inventory-method', [
            'tenantId' => $this->resolveTenantId(),
            'showMenu' => false,
        ]);
    }

    public function storeStepThree(Request $request, OnboardingFlowStateService $flowState): RedirectResponse
    {
        if (! $request->session()->has('onboarding.step2')) {
            return redirect('/register/step-2');
        }

        $validated = $request->validate([
            'valuation_method' => ['required', 'in:fifo,average'],
        ]);

        $stepTwo = $request->session()->get('onboarding.step2');

        Tenant::query()
            ->where('id', $stepTwo['tenant_id'])
            ->update(['inventory_valuation_method' => $validated['valuation_method']]);

        $flowState->storeInventoryMethodStep($request, $validated['valuation_method']);

        return redirect('/register/step-4');
    }

    public function showStepFour(Request $request): RedirectResponse|View
    {
        if (! $request->session()->has('onboarding.step3')) {
            return redirect('/register/step-3');
        }

        return view('onboarding-product-first', [
            'tenantId' => $this->resolveTenantId(),
            'showMenu' => false,
        ]);
    }

    public function storeStepFour(Request $request, OnboardingInitialProductService $initialProduct, OnboardingFlowStateService $flowState): RedirectResponse
    {
        if (! $request->session()->has('onboarding.step3')) {
            return redirect('/register/step-3');
        }

        $stepTwo = $request->session()->get('onboarding.step2');
        $owner = User::query()->find($stepTwo['user_id'] ?? null);
        $skipProduct = $request->boolean('skip_product');

        if (! $skipProduct) {
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:190'],
                'sku' => ['nullable', 'string', 'max:100'],
                'category' => ['nullable', 'string', 'max:100'],
                'hpp_average' => ['required', 'numeric', 'min:0'],
                'price' => ['required', 'numeric', 'min:0'],
                'initial_stock' => ['required', 'numeric', 'min:0'],
                'variants' => ['nullable', 'array'],
                'variants.*.id' => ['nullable', 'uuid'],
                'variants.*.sku' => ['required_with:variants', 'string', 'max:100'],
                'variants.*.name' => ['required_with:variants', 'string', 'max:150'],
                'initial_stock_lines' => ['nullable', 'array'],
                'initial_stock_lines.*.product_variant_id' => ['nullable', 'uuid'],
                'initial_stock_lines.*.qty' => ['required_with:initial_stock_lines', 'numeric', 'min:0.0001'],
                'initial_stock_lines.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            ]);

            $sku = trim((string) ($validated['sku'] ?? ''));
            if ($sku === '') {
                $sku = 'SKU-'.strtoupper(substr(md5($validated['name'].microtime(true)), 0, 8));
            }

            $initialProduct->bootstrap(
                step2: $stepTwo,
                payload: array_merge($validated, ['sku' => $sku]),
            );
        }

        $flowState->complete($request, $owner);

        return redirect('/pos/dashboard');
    }

    private function resolveTenantId(): ?string
    {
        if (! Schema::hasTable('tenants')) {
            return null;
        }

        return Tenant::query()->value('id');
    }
}