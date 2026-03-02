<?php

use App\Models\AccountMapping;
use App\Models\ChartOfAccount;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

Route::get('/', function () {
    $tenantId = Schema::hasTable('tenants')
        ? Tenant::query()->value('id')
        : null;

    if ($tenantId && Schema::hasTable('users')) {
        $hasUsers = DB::table('users')
            ->where('tenant_id', $tenantId)
            ->exists();

        if (! $hasUsers) {
            return redirect('/register/step-1');
        }
    }

    return view('welcome', [
        'tenantId' => $tenantId,
    ]);
});

Route::get('/register', function () {
    return redirect('/register/step-1');
});

Route::get('/register/step-1', function (Request $request) {
    if ($request->session()->has('onboarding.completed')) {
        return redirect('/');
    }

    $tenantId = Schema::hasTable('tenants') ? Tenant::query()->value('id') : null;

    return view('onboarding-register-user', [
        'tenantId' => $tenantId,
        'showMenu' => false,
    ]);
});

Route::post('/register/step-1', function (Request $request) {
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
});

Route::get('/register/step-2', function (Request $request) {
    if (! $request->session()->has('onboarding.step1')) {
        return redirect('/register/step-1');
    }

    $tenantId = Schema::hasTable('tenants') ? Tenant::query()->value('id') : null;

    return view('onboarding-company-profile', [
        'tenantId' => $tenantId,
        'showMenu' => false,
    ]);
});

Route::post('/register/step-2', function (Request $request) {
    if (! $request->session()->has('onboarding.step1')) {
        return redirect('/register/step-1');
    }

    $validated = $request->validate([
        'store_name' => ['required', 'string', 'max:190'],
        'operational_address' => ['nullable', 'string', 'max:500'],
        'business_category' => ['nullable', 'in:fnb,retail,service,other'],
        'timezone' => ['nullable', 'string', 'max:64'],
    ]);

    $tenant = Tenant::query()->first();
    $timezone = $validated['timezone'] ?? 'Asia/Jakarta';

    if ($tenant) {
        $tenant->name = $validated['store_name'];
        $tenant->timezone = $timezone;
        $tenant->save();
    } else {
        $tenant = Tenant::query()->create([
            'name' => $validated['store_name'],
            'timezone' => $timezone,
            'inventory_valuation_method' => 'average',
        ]);
    }

    $userData = $request->session()->get('onboarding.step1');

    $owner = User::query()
        ->where('tenant_id', $tenant->id)
        ->where('email', $userData['email'])
        ->first();

    if (! $owner) {
        $owner = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $userData['name'],
            'email' => $userData['email'],
            'password' => Hash::make($userData['password']),
            'failed_login_attempts' => 0,
        ]);
    }

    $request->session()->put('onboarding.step2', [
        'tenant_id' => $tenant->id,
        'user_id' => $owner->id,
        'store_name' => $validated['store_name'],
        'operational_address' => $validated['operational_address'] ?? null,
        'business_category' => $validated['business_category'] ?? null,
    ]);

    return redirect('/register/step-3');
});

Route::get('/register/step-3', function (Request $request) {
    if (! $request->session()->has('onboarding.step2')) {
        return redirect('/register/step-2');
    }

    $tenantId = Schema::hasTable('tenants') ? Tenant::query()->value('id') : null;

    return view('onboarding-inventory-method', [
        'tenantId' => $tenantId,
        'showMenu' => false,
    ]);
});

Route::post('/register/step-3', function (Request $request) {
    if (! $request->session()->has('onboarding.step2')) {
        return redirect('/register/step-2');
    }

    $validated = $request->validate([
        'valuation_method' => ['required', 'in:fifo,average'],
    ]);

    $step2 = $request->session()->get('onboarding.step2');

    Tenant::query()
        ->where('id', $step2['tenant_id'])
        ->update(['inventory_valuation_method' => $validated['valuation_method']]);

    $request->session()->put('onboarding.step3', [
        'valuation_method' => $validated['valuation_method'],
    ]);

    return redirect('/register/step-4');
});

Route::get('/register/step-4', function (Request $request) {
    if (! $request->session()->has('onboarding.step3')) {
        return redirect('/register/step-3');
    }

    $tenantId = Schema::hasTable('tenants') ? Tenant::query()->value('id') : null;

    return view('onboarding-product-first', [
        'tenantId' => $tenantId,
        'showMenu' => false,
    ]);
});

Route::post('/register/step-4', function (Request $request) {
    if (! $request->session()->has('onboarding.step3')) {
        return redirect('/register/step-3');
    }

    $step2 = $request->session()->get('onboarding.step2');
    $skipProduct = $request->boolean('skip_product');

    if (! $skipProduct) {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'sku' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
            'hpp_average' => ['required', 'numeric', 'min:0'],
            'price' => ['required', 'numeric', 'min:0'],
            'initial_stock' => ['required', 'numeric', 'min:0'],
        ]);

        $sku = trim((string) ($validated['sku'] ?? ''));
        if ($sku === '') {
            $sku = 'SKU-'.strtoupper(substr(md5($validated['name'].microtime(true)), 0, 8));
        }

        Product::query()->create([
            'tenant_id' => $step2['tenant_id'],
            'name' => $validated['name'],
            'sku' => $sku,
            'price' => $validated['price'],
            'hpp_average' => $validated['hpp_average'],
            'min_stock' => 0,
            'track_stock' => true,
            'is_active' => true,
            'created_by' => $step2['user_id'] ?? null,
            'updated_by' => $step2['user_id'] ?? null,
        ]);
    }

    $request->session()->put('onboarding.completed', true);
    $request->session()->forget([
        'onboarding.step1',
        'onboarding.step2',
        'onboarding.step3',
    ]);

    return redirect('/');
});

Route::get('/menu', function () {
    $tenantId = Tenant::query()->value('id');

    return view('menu', [
        'tenantId' => $tenantId,
    ]);
});

Route::get('/menu/{section}', function (string $section) {
    $tenantId = Tenant::query()->value('id');
    $sections = [
        'management' => [
            'title' => 'Manajemen',
            'items' => [
                ['label' => 'Barang atau Jasa', 'icon' => 'inventory_2', 'href' => '/products'],
                ['label' => 'Kategori Barang', 'icon' => 'grid_view', 'href' => '/categories'],
                ['label' => 'Manajemen Stok', 'icon' => 'conveyor_belt', 'href' => '/inventory'],
                ['label' => 'Pembelian Barang', 'icon' => 'shopping_cart', 'href' => '/purchases/new'],
                ['label' => 'Pelanggan dan Supplier', 'icon' => 'groups', 'href' => '/customers'],
                ['label' => 'Hutang dan Piutang', 'icon' => 'receipt_long', 'href' => '/lock-periods'],
                ['label' => 'Diskon, Pajak, dan Biaya', 'icon' => 'percent', 'href' => '/settings/tax-promo'],
                ['label' => 'Stok Opname', 'icon' => 'edit_note', 'href' => '/stock-adjustment'],
                ['label' => 'Marketing', 'icon' => 'stars', 'href' => '/loyalty'],
            ],
        ],
        'sales' => [
            'title' => 'Transaksi Penjualan',
            'items' => [
                ['label' => 'Kasir POS', 'icon' => 'point_of_sale', 'href' => '/kasir'],
                ['label' => 'Riwayat Penjualan', 'icon' => 'history', 'href' => '/sales-history'],
                ['label' => 'Void Transaksi', 'icon' => 'assignment_return', 'href' => '/sales-void-refund'],
            ],
        ],
        'purchases' => [
            'title' => 'Pembelian ke Supplier',
            'items' => [
                ['label' => 'Daftar Pembelian', 'icon' => 'receipt_long', 'href' => '/purchases/detail'],
                ['label' => 'Buat Pembelian Baru', 'icon' => 'add_circle', 'href' => '/purchases/new'],
                ['label' => 'Detail Pembelian', 'icon' => 'description', 'href' => '/purchases/detail'],
                ['label' => 'Penerimaan Barang', 'icon' => 'inventory_2', 'href' => '/receiving'],
                ['label' => 'Retur Pembelian', 'icon' => 'assignment_return', 'href' => '/purchase-returns'],
            ],
        ],
        'finance' => [
            'title' => 'Keuangan',
            'items' => [
                ['label' => 'Keuangan', 'icon' => 'account_balance_wallet', 'href' => '/finance-summary'],
                ['label' => 'Riwayat Transaksi Kas/Bank', 'icon' => 'history', 'href' => '/lock-periods'],
            ],
        ],
        'shift' => [
            'title' => 'Shift',
            'items' => [
                ['label' => 'Buka Shift', 'icon' => 'update', 'href' => '/shift/open'],
                ['label' => 'Tutup Shift', 'icon' => 'lock_clock', 'href' => '/shift/close'],
                ['label' => 'Riwayat Shift', 'icon' => 'history', 'href' => '/shift/close-summary'],
            ],
        ],
        'reports' => [
            'title' => 'Laporan',
            'items' => [
                ['label' => 'Ringkasan Penjualan', 'icon' => 'bar_chart', 'href' => '/analytics'],
                ['label' => 'Laporan Laba Rugi', 'icon' => 'trending_up', 'href' => '/finance-summary'],
                ['label' => 'Laporan Arus Kas', 'icon' => 'savings', 'href' => '/finance-summary'],
                ['label' => 'Laporan Penjualan', 'icon' => 'list_alt', 'href' => '/sales-history'],
                ['label' => 'Laporan Pembelian', 'icon' => 'shopping_cart', 'href' => '/purchases/detail'],
                ['label' => 'Laporan Persediaan', 'icon' => 'inventory_2', 'href' => '/stock-card'],
                ['label' => 'Laporan Pembayaran', 'icon' => 'payments', 'href' => '/finance-summary'],
            ],
        ],
        'settings' => [
            'title' => 'Pengaturan',
            'items' => [
                ['label' => 'Toko', 'icon' => 'store', 'href' => '/branches'],
                ['label' => 'Bagan Akun (COA)', 'icon' => 'account_balance', 'href' => '/settings/chart-of-accounts'],
                ['label' => 'Database / Sinkronisasi', 'icon' => 'database', 'href' => '/menu'],
                ['label' => 'Printer & Struk', 'icon' => 'print', 'href' => '/menu'],
                ['label' => 'Manajemen Staff', 'icon' => 'person', 'href' => '/users'],
                ['label' => 'E-Wallet', 'icon' => 'account_balance_wallet', 'href' => '/menu'],
                ['label' => 'Notifikasi', 'icon' => 'notifications', 'href' => '/menu'],
                ['label' => 'Pembayaran / Langganan', 'icon' => 'payments', 'href' => '/menu'],
                ['label' => 'Pengaturan Transaksi', 'icon' => 'tune', 'href' => '/settings/tax-promo'],
                ['label' => 'Valuation Persediaan', 'icon' => 'inventory', 'href' => '/settings/inventory-valuation'],
                ['label' => 'Audit Valuation', 'icon' => 'history', 'href' => '/settings/inventory-valuation-audit'],
                ['label' => 'Observability', 'icon' => 'monitoring', 'href' => '/settings/observability'],
            ],
        ],
        'other' => [
            'title' => 'Lainnya',
            'items' => [
                ['label' => 'Referral', 'icon' => 'group', 'href' => '/menu'],
                ['label' => 'Toko Online Saya', 'icon' => 'storefront', 'href' => '/menu'],
                ['label' => 'Pusat Bantuan', 'icon' => 'support_agent', 'href' => '/menu'],
            ],
        ],
    ];

    if (!array_key_exists($section, $sections)) {
        abort(404);
    }

    return view('menu-section', [
        'tenantId' => $tenantId,
        'title' => $sections[$section]['title'],
        'items' => $sections[$section]['items'],
    ]);
});

Route::get('/inventory', function () {
    $tenantId = Tenant::query()->value('id');

    return view('inventory', [
        'tenantId' => $tenantId,
    ]);
});

Route::get('/stock-card', function () {
    $tenantId = Tenant::query()->value('id');

    return view('stock-card', [
        'tenantId' => $tenantId,
    ]);
});

Route::get('/settings/chart-of-accounts', function () {
    $tenantId = Tenant::query()->value('id');

    return view('chart-of-accounts', [
        'tenantId' => $tenantId,
    ]);
});

Route::get('/settings/chart-of-accounts/{account}', function (string $account) {
    $tenantId = Tenant::query()->value('id');

    return view('chart-of-accounts-detail', [
        'tenantId' => $tenantId,
        'accountId' => $account,
    ]);
});

Route::get('/settings/bank-accounts/{account}', function (string $account) {
    $tenantId = Tenant::query()->value('id');

    return view('bank-account-detail', [
        'tenantId' => $tenantId,
        'accountId' => $account,
    ]);
});

Route::get('/stock-adjustment', function () {
    $tenantId = Tenant::query()->value('id');

    return view('stock-adjustment', [
        'tenantId' => $tenantId,
    ]);
});

Route::get('/analytics', function () {
    $tenantId = Tenant::query()->value('id');

    return view('analytics', [
        'tenantId' => $tenantId,
    ]);
});

Route::get('/products/new', function () {
    $tenantId = Tenant::query()->value('id');

    return view('product-create', [
        'tenantId' => $tenantId,
    ]);
});

Route::get('/products/{product}/edit', function (string $product) {
    $tenantId = Tenant::query()->value('id');

    return view('product-edit', [
        'tenantId' => $tenantId,
        'productId' => $product,
    ]);
});

Route::get('/categories', function () {
    $tenantId = Tenant::query()->value('id');

    return view('categories', [
        'tenantId' => $tenantId,
    ]);
});

Route::get('/categories/new', function () {
    $tenantId = Tenant::query()->value('id');

    return view('category-create', [
        'tenantId' => $tenantId,
    ]);
});

Route::get('/categories/{category}/edit', function (string $category) {
    $tenantId = Tenant::query()->value('id');

    return view('category-edit', [
        'tenantId' => $tenantId,
        'categoryId' => $category,
    ]);
});

Route::get('/customers', function () {
    $tenantId = Tenant::query()->value('id');

    return view('customers', [
        'tenantId' => $tenantId,
    ]);
});

Route::get('/suppliers', function () {
    $tenantId = Tenant::query()->value('id');

    return view('suppliers', [
        'tenantId' => $tenantId,
    ]);
});

Route::get('/receiving', function () {
    $tenantId = Tenant::query()->value('id');

    return view('receiving', [
        'tenantId' => $tenantId,
    ]);
});

Route::get('/branches', function () {
    $tenantId = Tenant::query()->value('id');

    return view('branches', [
        'tenantId' => $tenantId,
    ]);
});

Route::get('/products', function () {
    $tenantId = Tenant::query()->value('id');

    return view('products', [
        'tenantId' => $tenantId,
    ]);
});

Route::get('/products/detail', function () {
    $tenantId = Tenant::query()->value('id');

    return view('product-detail', [
        'tenantId' => $tenantId,
    ]);
});

Route::get('/sales-history', function () {
    $tenantId = Tenant::query()->value('id');

    return view('sales-history', [
        'tenantId' => $tenantId,
    ]);
});

Route::get('/kasir', function () {
    $tenantId = Tenant::query()->value('id');

    return view('kasir', [
        'tenantId' => $tenantId,
    ]);
});

Route::get('/payment-method', function () {
    $tenantId = Tenant::query()->value('id');

    $bankParent = ChartOfAccount::query()
        ->where('code', '1102')
        ->whereNull('parent_id')
        ->first();

    if (! $bankParent) {
        $bankParent = ChartOfAccount::query()
            ->whereNull('parent_id')
            ->where('name', 'like', '%bank%')
            ->orderBy('code')
            ->first();
    }

    if (! $bankParent) {
        $mappedId = AccountMapping::query()->value('bank_account_id');
        if ($mappedId) {
            $mapped = ChartOfAccount::query()->find($mappedId);
            if ($mapped) {
                $bankParent = $mapped->parent_id
                    ? ChartOfAccount::query()->find($mapped->parent_id)
                    : $mapped;
            }
        }
    }

    $bankAccounts = collect();
    if ($bankParent) {
        $bankAccounts = ChartOfAccount::query()
            ->where('parent_id', $bankParent->id)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'parent_id'])
            ->filter(fn ($account) => preg_match('/^11\d+$/', (string) ($account->code ?? '')));

        if ($bankAccounts->isEmpty()) {
            $bankAccounts = preg_match('/^11\d+$/', (string) ($bankParent->code ?? ''))
                ? collect([$bankParent])
                : collect();
        }
    }

    return view('payment-method', [
        'tenantId' => $tenantId,
        'bankAccounts' => $bankAccounts,
    ]);
});

Route::get('/receipt-preview', function () {
    $tenantId = Tenant::query()->value('id');

    return view('receipt-preview', [
        'tenantId' => $tenantId,
    ]);
});

Route::get('/sales-detail', function () {
    $tenantId = Tenant::query()->value('id');

    return view('sales-detail', [
        'tenantId' => $tenantId,
    ]);
});

Route::get('/sales-void-refund', function () {
    $tenantId = Tenant::query()->value('id');

    return view('void-refund', [
        'tenantId' => $tenantId,
    ]);
});

Route::get('/shift/open', function () {
    $tenantId = Tenant::query()->value('id');

    return view('shift-open', [
        'tenantId' => $tenantId,
    ]);
});

Route::get('/branches/edit', function () {
    $tenantId = Tenant::query()->value('id');

    return view('branch-edit', [
        'tenantId' => $tenantId,
    ]);
});

Route::get('/purchases/new', function () {
    $tenantId = Tenant::query()->value('id');

    return view('purchase-create', [
        'tenantId' => $tenantId,
    ]);
});

Route::get('/receiving/action', function () {
    $tenantId = Tenant::query()->value('id');

    return view('receiving-action', [
        'tenantId' => $tenantId,
    ]);
});

Route::get('/shift/close', function () {
    $tenantId = Tenant::query()->value('id');

    return view('shift-close', [
        'tenantId' => $tenantId,
    ]);
});

Route::get('/shift/close-summary', function () {
    $tenantId = Tenant::query()->value('id');

    return view('shift-close-summary', [
        'tenantId' => $tenantId,
    ]);
});

Route::get('/purchases/detail', function () {
    $tenantId = Tenant::query()->value('id');

    return view('purchase-detail', [
        'tenantId' => $tenantId,
    ]);
});

Route::get('/purchase-returns', function () {
    $tenantId = Tenant::query()->value('id');

    return view('purchase-return', [
        'tenantId' => $tenantId,
    ]);
});

Route::get('/stock-alerts', function () {
    $tenantId = Tenant::query()->value('id');

    return view('stock-alerts', [
        'tenantId' => $tenantId,
    ]);
});

Route::get('/users', function () {
    $tenantId = Tenant::query()->value('id');

    return view('users', [
        'tenantId' => $tenantId,
    ]);
});

Route::get('/settings/tax-promo', function () {
    $tenantId = Tenant::query()->value('id');

    return view('tax-promo', [
        'tenantId' => $tenantId,
    ]);
});

Route::get('/settings/inventory-valuation', function () {
    $tenantId = Tenant::query()->value('id');

    return view('inventory-valuation-settings', [
        'tenantId' => $tenantId,
    ]);
});

Route::get('/settings/inventory-valuation-audit', function () {
    $tenantId = Tenant::query()->value('id');

    return view('inventory-valuation-audit', [
        'tenantId' => $tenantId,
    ]);
});

Route::get('/settings/observability', function () {
    $tenantId = Tenant::query()->value('id');

    return view('observability', [
        'tenantId' => $tenantId,
    ]);
});

Route::get('/finance-summary', function () {
    $tenantId = Tenant::query()->value('id');

    return view('finance-summary', [
        'tenantId' => $tenantId,
    ]);
});

Route::get('/lock-periods', function () {
    $tenantId = Tenant::query()->value('id');

    return view('lock-periods', [
        'tenantId' => $tenantId,
    ]);
});

Route::get('/stock-transfers', function () {
    $tenantId = Tenant::query()->value('id');

    return view('stock-transfers', [
        'tenantId' => $tenantId,
    ]);
});

Route::get('/loyalty', function () {
    $tenantId = Tenant::query()->value('id');

    return view('loyalty', [
        'tenantId' => $tenantId,
    ]);
});
