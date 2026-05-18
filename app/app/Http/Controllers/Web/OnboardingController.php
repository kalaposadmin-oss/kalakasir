<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Services\OnboardingFlowStateService;
use App\Services\OnboardingInitialProductService;
use App\Services\OnboardingProvisioningService;
use App\Services\SubscriptionService;
use App\Support\FeatureGateConfig;
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

        app(OnboardingFlowStateService::class)->storeAccountStep($request, $validated);

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
        $existingOwnerId = (string) $request->session()->get('onboarding.step2.user_id', '');

        [$tenant, $owner] = $provisioning->completeCompanyProfileStep(
            userData: $userData,
            companyData: array_merge($validated, ['timezone' => $timezone]),
            existingTenantId: $existingTenantId !== '' ? $existingTenantId : null,
            existingOwnerId: $existingOwnerId !== '' ? $existingOwnerId : null,
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

        $skipProduct = $request->boolean('skip_product');

        if (! $skipProduct) {
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:190'],
                'sku' => ['nullable', 'string', 'max:100'],
                'category' => ['nullable', 'string', 'max:100'],
                'hpp_average' => ['nullable', 'numeric', 'min:0'],
                'price' => ['nullable', 'numeric', 'min:0'],
                'initial_stock' => ['required', 'numeric', 'min:0'],
                'variants' => ['nullable', 'array'],
                'variants.*.id' => ['nullable', 'uuid'],
                'variants.*.sku' => ['required_with:variants', 'string', 'max:100'],
                'variants.*.name' => ['required_with:variants', 'string', 'max:150'],
                'initial_stock_lines' => ['nullable', 'array'],
                'initial_stock_lines.*.product_variant_id' => ['nullable', 'uuid'],
                'initial_stock_lines.*.qty' => ['required_with:initial_stock_lines', 'numeric', 'min:0.0001'],
                'initial_stock_lines.*.unit_cost' => ['nullable', 'numeric', 'min:0.01'],
            ]);

            $sku = trim((string) ($validated['sku'] ?? ''));
            if ($sku === '') {
                $sku = 'SKU-'.strtoupper(substr(md5($validated['name'].microtime(true)), 0, 8));
            }

            $draftPayload = array_merge($validated, ['sku' => $sku]);
            $initialProduct->validateDraftPayload($draftPayload);
            $flowState->storeInitialProductStep($request, $draftPayload, false);
        } else {
            $flowState->storeInitialProductStep($request, [], true);
        }

        return redirect('/register/review');
    }

    public function showReview(Request $request): RedirectResponse|View
    {
        if (! $request->session()->has('onboarding.step4')) {
            return redirect('/register/step-4');
        }

        return view('onboarding-review', [
            'tenantId' => $this->resolveTenantId(),
            'showMenu' => false,
            'stepOne' => (array) $request->session()->get('onboarding.step1', []),
            'stepTwo' => (array) $request->session()->get('onboarding.step2', []),
            'stepThree' => (array) $request->session()->get('onboarding.step3', []),
            'stepFour' => (array) $request->session()->get('onboarding.step4', []),
            'planOptions' => $this->onboardingPlanOptions(),
            'selectedPlan' => old('selected_plan', $this->resolveSelectedOnboardingPlan($request)),
        ]);
    }

    public function completeReview(Request $request, OnboardingInitialProductService $initialProduct, OnboardingFlowStateService $flowState, SubscriptionService $subscriptionService): RedirectResponse
    {
        if (! $request->session()->has('onboarding.step4')) {
            return redirect('/register/step-4');
        }

        $validated = $request->validate([
            'selected_plan' => ['required', 'in:S,M,P'],
        ]);

        $stepTwo = (array) $request->session()->get('onboarding.step2', []);
        $stepFour = (array) $request->session()->get('onboarding.step4', []);
        $owner = User::query()->find($stepTwo['user_id'] ?? null);
        $skipProduct = (bool) ($stepFour['skip_product'] ?? false);

        if (filled($stepTwo['tenant_id'] ?? null)) {
            Tenant::query()
                ->where('id', $stepTwo['tenant_id'])
                ->update(['plan' => FeatureGateConfig::normalizePlan($validated['selected_plan'])]);

            $tenant = Tenant::query()->find($stepTwo['tenant_id']);
            if ($tenant) {
                $subscriptionService->ensureCurrentSubscription($tenant, $validated['selected_plan']);
            }
        }

        if (! $skipProduct) {
            $payload = (array) ($stepFour['product'] ?? []);
            if (($payload['name'] ?? null) === null) {
                return redirect('/register/step-4');
            }

            $initialProduct->bootstrap(
                step2: $stepTwo,
                payload: $payload,
            );
        }

        $flowState->complete($request, $owner);

        return redirect('/pos/dashboard');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function onboardingPlanOptions(): array
    {
        $labels = FeatureGateConfig::planLabels();

        return [
            [
                'key' => 'S',
                'label' => $labels['S'] ?? 'Starter',
                'badge' => 'Paling sederhana',
                'accent' => 'slate',
                'recommended' => false,
                'fit_for' => 'Toko baru mulai jualan dan operasional dasar.',
                'summary' => 'Fokus ke kasir, stok dasar, supplier, dan laporan inti.',
                'highlights' => [
                    'Kasir dan checkout harian siap dipakai.',
                    'Kelola produk, kategori, stok, dan penyesuaian stok dasar.',
                    'Lihat laporan stok dasar, penjualan, dan pembelian.',
                    'Kelola supplier, akun kas/bank, dan billing langganan.',
                ],
            ],
            [
                'key' => 'M',
                'label' => $labels['M'] ?? 'Medium',
                'badge' => 'Paling cocok untuk mayoritas UMKM',
                'accent' => 'primary',
                'recommended' => true,
                'fit_for' => 'Bisnis yang mulai rapi, punya tim, dan butuh kontrol lebih.',
                'summary' => 'Termasuk semua Starter, ditambah pelanggan, loyalty, pajak, cabang, dan ekspor laporan.',
                'highlights' => [
                    'Kelola pelanggan, loyalty, dan cetak ringkasan shift.',
                    'Aktifkan retur pembelian, transfer stok, produksi, dan bahan baku.',
                    'Ekspor laporan PDF/CSV untuk kebutuhan operasional dan review.',
                    'Atur pajak, promo, pengguna, dan cabang dari backoffice.',
                ],
            ],
            [
                'key' => 'P',
                'label' => $labels['P'] ?? 'Pro',
                'badge' => 'Kontrol lanjutan',
                'accent' => 'amber',
                'recommended' => false,
                'fit_for' => 'Owner yang butuh audit mendalam, kontrol keuangan, dan integrasi.',
                'summary' => 'Termasuk semua Medium, ditambah audit stok detail, observability, dan pengaturan premium.',
                'highlights' => [
                    'Gunakan kartu stok detail untuk investigasi pergerakan barang.',
                    'Buka neraca, lock period, dan valuation report premium.',
                    'Pantau observability, anomaly, dan health operasional.',
                    'Akses integrasi lanjutan untuk kebutuhan bisnis yang lebih kompleks.',
                ],
            ],
        ];
    }

    private function resolveSelectedOnboardingPlan(Request $request): string
    {
        $stepTwo = (array) $request->session()->get('onboarding.step2', []);
        $tenantId = (string) ($stepTwo['tenant_id'] ?? '');

        if ($tenantId !== '') {
            $tenantPlan = Tenant::query()->where('id', $tenantId)->value('plan');
            if (filled($tenantPlan)) {
                return FeatureGateConfig::normalizePlan((string) $tenantPlan);
            }
        }

        return 'M';
    }

    private function resolveTenantId(): ?string
    {
        if (! Schema::hasTable('tenants')) {
            return null;
        }

        return Tenant::query()->value('id');
    }
}