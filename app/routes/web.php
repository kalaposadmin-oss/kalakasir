<?php

use App\Http\Controllers\Web\BackofficePageController;
use App\Http\Controllers\Web\PortalAuthController;
use App\Http\Controllers\Web\LegacyWebRedirectController;
use App\Http\Controllers\Web\MenuController;
use App\Http\Controllers\Web\OnboardingController;
use App\Http\Controllers\Web\PlatformDashboardController;
use App\Http\Controllers\Web\PosPageController;
use App\Http\Controllers\Web\PortalSessionController;
use App\Http\Controllers\Web\TenantViewController;
use Illuminate\Support\Facades\Route;

Route::get('/', [OnboardingController::class, 'rootRedirect']);

Route::get('/register', [OnboardingController::class, 'registerRedirect']);

Route::get('/register/step-1', [OnboardingController::class, 'showStepOne']);

Route::post('/register/step-1', [OnboardingController::class, 'storeStepOne']);

Route::get('/register/step-2', [OnboardingController::class, 'showStepTwo']);

Route::post('/register/step-2', [OnboardingController::class, 'storeStepTwo']);

Route::get('/register/step-3', [OnboardingController::class, 'showStepThree']);

Route::post('/register/step-3', [OnboardingController::class, 'storeStepThree']);

Route::get('/register/step-4', [OnboardingController::class, 'showStepFour']);

Route::post('/register/step-4', [OnboardingController::class, 'storeStepFour']);

Route::get('/menu', [MenuController::class, 'index']);

Route::get('/menu/{section}', [MenuController::class, 'section']);

Route::get('/inventory', [LegacyWebRedirectController::class, 'to'])->defaults('target', '/backoffice/inventory');

Route::get('/stock-card', [LegacyWebRedirectController::class, 'entityOrFallback'])
    ->defaults('queryKey', 'product_id')
    ->defaults('detailPrefix', '/backoffice/inventory/products/')
    ->defaults('detailSuffix', '/stock-card')
    ->defaults('fallbackTarget', '/backoffice/reports/inventory/stock-card');

Route::get('/settings/chart-of-accounts', [LegacyWebRedirectController::class, 'to'])->defaults('target', '/backoffice/settings/chart-of-accounts');

Route::get('/settings/chart-of-accounts/{account}', [LegacyWebRedirectController::class, 'to'])
    ->defaults('target', '/backoffice/settings/chart-of-accounts/{account}');

Route::get('/settings/bank-accounts/{account}', [LegacyWebRedirectController::class, 'to'])
    ->defaults('target', '/backoffice/settings/bank-accounts/{account}');

Route::get('/stock-adjustment', [LegacyWebRedirectController::class, 'to'])->defaults('target', '/backoffice/inventory/stock-adjustment');

Route::get('/analytics', [LegacyWebRedirectController::class, 'to'])->defaults('target', '/backoffice/dashboard');

Route::get('/products/new', [LegacyWebRedirectController::class, 'to'])->defaults('target', '/backoffice/products/new');

Route::get('/products/{product}/edit', [LegacyWebRedirectController::class, 'to'])
    ->defaults('target', '/backoffice/products/{product}/edit');

Route::get('/categories', [LegacyWebRedirectController::class, 'to'])->defaults('target', '/backoffice/categories');

Route::get('/categories/new', [LegacyWebRedirectController::class, 'to'])->defaults('target', '/backoffice/categories/new');

Route::get('/categories/{category}/edit', [LegacyWebRedirectController::class, 'to'])
    ->defaults('target', '/backoffice/categories/{category}/edit');

Route::get('/customers', [LegacyWebRedirectController::class, 'to'])->defaults('target', '/backoffice/customers');

Route::get('/suppliers', [LegacyWebRedirectController::class, 'to'])->defaults('target', '/backoffice/suppliers');

Route::get('/receiving', [LegacyWebRedirectController::class, 'entityOrFallback'])
    ->defaults('queryKey', 'purchase_id')
    ->defaults('detailPrefix', '/backoffice/receiving/')
    ->defaults('fallbackTarget', '/backoffice/receiving');

Route::get('/branches', [LegacyWebRedirectController::class, 'to'])->defaults('target', '/backoffice/branches');

Route::get('/products', [LegacyWebRedirectController::class, 'to'])->defaults('target', '/backoffice/products');

Route::get('/products/detail', [LegacyWebRedirectController::class, 'entityOrFallback'])
    ->defaults('queryKey', 'product_id')
    ->defaults('detailPrefix', '/backoffice/products/')
    ->defaults('fallbackTarget', '/backoffice/products/detail');

Route::get('/sales-history', [LegacyWebRedirectController::class, 'salesHistory']);

Route::get('/kasir', [LegacyWebRedirectController::class, 'to'])->defaults('target', '/pos/checkout');

Route::get('/payment-method', [LegacyWebRedirectController::class, 'to'])->defaults('target', '/pos/payment-method');

Route::get('/sales-void-refund', [LegacyWebRedirectController::class, 'salesVoidRefund']);

Route::get('/shift/open', [LegacyWebRedirectController::class, 'to'])->defaults('target', '/pos/shifts/open');

Route::get('/branches/edit', [LegacyWebRedirectController::class, 'entityOrFallback'])
    ->defaults('queryKey', 'branch_id')
    ->defaults('detailPrefix', '/backoffice/branches/')
    ->defaults('detailSuffix', '/edit')
    ->defaults('fallbackTarget', '/backoffice/branches/edit');

Route::get('/purchases/new', [LegacyWebRedirectController::class, 'to'])->defaults('target', '/backoffice/purchases/new');

Route::get('/receiving/action', [LegacyWebRedirectController::class, 'entityOrFallback'])
    ->defaults('queryKey', 'purchase_id')
    ->defaults('detailPrefix', '/backoffice/receiving/')
    ->defaults('detailSuffix', '/action')
    ->defaults('fallbackTarget', '/backoffice/receiving/action');

Route::get('/shift/close', [LegacyWebRedirectController::class, 'to'])->defaults('target', '/pos/shifts/close');

Route::get('/shift/close-summary', [LegacyWebRedirectController::class, 'entityOrFallback'])
    ->defaults('queryKey', 'shift_id')
    ->defaults('detailPrefix', '/pos/shifts/')
    ->defaults('detailSuffix', '/summary')
    ->defaults('fallbackTarget', '/pos/shifts/summary');

Route::get('/purchases/detail', [LegacyWebRedirectController::class, 'entityOrFallback'])
    ->defaults('queryKey', 'purchase_id')
    ->defaults('detailPrefix', '/backoffice/purchases/')
    ->defaults('fallbackTarget', '/backoffice/purchases/detail');

Route::get('/purchase-returns', [LegacyWebRedirectController::class, 'to'])->defaults('target', '/backoffice/purchases/returns');

Route::get('/stock-alerts', [LegacyWebRedirectController::class, 'to'])->defaults('target', '/backoffice/reports/inventory/stock-alerts');

Route::get('/users', [LegacyWebRedirectController::class, 'to'])->defaults('target', '/backoffice/users');

Route::get('/settings/tax-promo', [LegacyWebRedirectController::class, 'to'])->defaults('target', '/backoffice/settings/tax-promo');

Route::get('/settings/inventory-valuation', [LegacyWebRedirectController::class, 'to'])->defaults('target', '/backoffice/settings/inventory-valuation');

Route::get('/settings/inventory-valuation-audit', [LegacyWebRedirectController::class, 'to'])->defaults('target', '/backoffice/settings/inventory-valuation-audit');

Route::get('/settings/observability', [LegacyWebRedirectController::class, 'to'])->defaults('target', '/backoffice/settings/observability');

Route::get('/finance-summary', [LegacyWebRedirectController::class, 'to'])->defaults('target', '/backoffice/finance/summary');

Route::get('/lock-periods', [LegacyWebRedirectController::class, 'to'])->defaults('target', '/backoffice/finance/lock-periods');

Route::get('/stock-transfers', [LegacyWebRedirectController::class, 'to'])->defaults('target', '/backoffice/inventory/stock-transfers');

Route::get('/production-batches', [LegacyWebRedirectController::class, 'to'])->defaults('target', '/backoffice/inventory/production-batches');

Route::get('/production-batches/{batch}', [LegacyWebRedirectController::class, 'to'])
    ->defaults('target', '/backoffice/inventory/production-batches/{batch}');

Route::get('/loyalty', [LegacyWebRedirectController::class, 'to'])->defaults('target', '/backoffice/loyalty');

Route::get('/login', [PortalAuthController::class, 'showGeneric']);
Route::post('/login', [PortalAuthController::class, 'loginGeneric']);

Route::middleware('canonical_portal_host:pos')->group(function () {
Route::get('/pos/login', [PortalAuthController::class, 'showPos']);
Route::post('/pos/login', [PortalAuthController::class, 'loginPos']);
});

Route::middleware('canonical_portal_host:backoffice')->group(function () {
Route::get('/backoffice/login', [PortalAuthController::class, 'showBackoffice']);
Route::post('/backoffice/login', [PortalAuthController::class, 'loginBackoffice']);
});

Route::get('/logout', [PortalSessionController::class, 'logout']);

Route::post('/logout', [PortalSessionController::class, 'logout']);

Route::get('/portal/switch/{portal}', [PortalSessionController::class, 'switch'])->middleware('auth');

Route::get('/sales-detail', [LegacyWebRedirectController::class, 'salesDetail']);

Route::get('/receipt-preview', [LegacyWebRedirectController::class, 'receiptPreview']);

Route::middleware(['canonical_portal_host:pos', 'web_access:pos,cashier|staff|warehouse|supervisor|admin|owner|superadmin,dash.overview'])->group(function () {
    Route::redirect('/pos', '/pos/dashboard');
    Route::get('/pos/dashboard', [TenantViewController::class, 'show'])
        ->defaults('view', 'pos-dashboard')
        ->defaults('data', ['showMenu' => true]);
    Route::get('/pos/checkout', [PosPageController::class, 'checkout']);
    Route::get('/pos/sales/{sale}', [PosPageController::class, 'saleDetail']);
    Route::get('/pos/sales/{sale}/void-refund', [PosPageController::class, 'saleVoidRefund']);
    Route::get('/pos/sales/{sale}/receipt', [PosPageController::class, 'saleReceipt']);
    Route::get('/pos/history', [TenantViewController::class, 'show'])
        ->defaults('view', 'sales-history')
        ->defaults('data', ['showMenu' => true, 'historyContext' => 'pos']);
    Route::get('/pos/void-refund', [TenantViewController::class, 'show'])
        ->defaults('view', 'void-refund')
        ->defaults('data', ['showMenu' => true]);
    Route::get('/pos/payment-method', [PosPageController::class, 'paymentMethod']);
    Route::get('/pos/receipt-preview', [PosPageController::class, 'receiptPreview']);
    Route::get('/pos/shifts/open', [TenantViewController::class, 'show'])
        ->defaults('view', 'shift-open')
        ->defaults('data', ['showMenu' => true]);
    Route::get('/pos/shifts/close', [TenantViewController::class, 'show'])
        ->defaults('view', 'shift-close')
        ->defaults('data', ['showMenu' => true]);
    Route::get('/pos/shifts/summary', [PosPageController::class, 'shiftSummaryIndex']);
    Route::get('/pos/shifts/{shift}/summary', [PosPageController::class, 'shiftSummaryDetail']);
  });

Route::middleware(['canonical_portal_host:backoffice', 'web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,dash.overview'])->group(function () {
    Route::redirect('/backoffice', '/backoffice/dashboard');
    Route::get('/backoffice/dashboard', [TenantViewController::class, 'show'])
        ->defaults('view', 'backoffice-dashboard')
        ->defaults('data', ['showMenu' => true]);
    Route::get('/backoffice/sales/{sale}', [BackofficePageController::class, 'saleDetail'])->middleware('web_access:backoffice,supervisor|admin|owner|superadmin,reports.summary');
    Route::get('/backoffice/reports', [TenantViewController::class, 'show'])->defaults('view', 'backoffice-reports')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,supervisor|admin|owner|superadmin,reports.summary');
    Route::get('/backoffice/reports/overview', [TenantViewController::class, 'show'])->defaults('view', 'backoffice-reports')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,supervisor|admin|owner|superadmin,reports.summary');
    Route::get('/backoffice/reports/inventory', [TenantViewController::class, 'show'])->defaults('view', 'reports-inventory')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,reports.inventory_view');
    Route::get('/backoffice/settings/subscription', [TenantViewController::class, 'show'])->defaults('view', 'subscription-current')->defaults('data', ['showMenu' => true]);
    Route::get('/backoffice/settings/subscription/history', [TenantViewController::class, 'show'])->defaults('view', 'subscription-history')->defaults('data', ['showMenu' => true]);
    Route::get('/backoffice/settings/subscription/invoices', [TenantViewController::class, 'show'])->defaults('view', 'subscription-invoices')->defaults('data', ['showMenu' => true]);
    Route::get('/backoffice/settings/subscription/change', [TenantViewController::class, 'show'])->defaults('view', 'subscription-change')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,admin|owner|superadmin,dash.overview');
    Route::get('/backoffice/settings/subscription/features', [TenantViewController::class, 'show'])->defaults('view', 'subscription-features')->defaults('data', ['showMenu' => true]);
    Route::get('/backoffice/settings/integrations', [TenantViewController::class, 'show'])->defaults('view', 'settings-integrations')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,settings.integrations');
    Route::get('/backoffice/settings/preferences', [TenantViewController::class, 'show'])->defaults('view', 'settings-preferences')->defaults('data', ['showMenu' => true]);
    Route::get('/backoffice/help/center', [TenantViewController::class, 'show'])->defaults('view', 'help-center')->defaults('data', ['showMenu' => true]);
    Route::get('/backoffice/help/about', [TenantViewController::class, 'show'])->defaults('view', 'help-about')->defaults('data', ['showMenu' => true]);
    Route::get('/backoffice/help/support', [TenantViewController::class, 'show'])->defaults('view', 'help-support')->defaults('data', ['showMenu' => true]);
    Route::get('/backoffice/marketing/online-store', [TenantViewController::class, 'show'])->defaults('view', 'marketing-online-store')->defaults('data', ['showMenu' => true]);
    Route::get('/backoffice/marketing/referral', [TenantViewController::class, 'show'])->defaults('view', 'marketing-referral')->defaults('data', ['showMenu' => true]);
    Route::get('/backoffice/products', [TenantViewController::class, 'show'])->defaults('view', 'products')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,dash.overview');
    Route::get('/backoffice/products/new', [TenantViewController::class, 'show'])->defaults('view', 'product-create')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,admin|owner|superadmin,dash.overview');
    Route::get('/backoffice/products/detail', [TenantViewController::class, 'show'])->defaults('view', 'product-detail')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,dash.overview');
    Route::get('/backoffice/products/{product}', [BackofficePageController::class, 'productDetail'])->middleware('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,dash.overview');
    Route::get('/backoffice/products/{product}/edit', [TenantViewController::class, 'showWithRouteParam'])
        ->defaults('view', 'product-edit')
        ->defaults('routeParam', 'product')
        ->defaults('viewDataKey', 'productId')
        ->defaults('data', ['showMenu' => true])
        ->middleware('web_access:backoffice,admin|owner|superadmin,dash.overview');
    Route::get('/backoffice/categories', [TenantViewController::class, 'show'])->defaults('view', 'categories')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,dash.overview');
    Route::get('/backoffice/categories/new', [TenantViewController::class, 'show'])->defaults('view', 'category-create')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,admin|owner|superadmin,dash.overview');
    Route::get('/backoffice/categories/{category}/edit', [TenantViewController::class, 'showWithRouteParam'])
        ->defaults('view', 'category-edit')
        ->defaults('routeParam', 'category')
        ->defaults('viewDataKey', 'categoryId')
        ->defaults('data', ['showMenu' => true])
        ->middleware('web_access:backoffice,admin|owner|superadmin,dash.overview');
    Route::get('/backoffice/inventory', [TenantViewController::class, 'show'])->defaults('view', 'inventory')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,dash.overview');
    Route::get('/backoffice/materials', [TenantViewController::class, 'show'])->defaults('view', 'materials')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,admin|owner|superadmin,dash.overview');
    Route::get('/backoffice/customers', [TenantViewController::class, 'show'])->defaults('view', 'customers')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,dash.overview');
    Route::get('/backoffice/suppliers', [TenantViewController::class, 'show'])->defaults('view', 'suppliers')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,dash.overview');
    Route::get('/backoffice/branches', [TenantViewController::class, 'show'])->defaults('view', 'branches')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,dash.overview');
    Route::get('/backoffice/branches/edit', [TenantViewController::class, 'show'])
        ->defaults('view', 'branch-edit')
        ->defaults('data', ['showMenu' => true, 'currentPath' => '/backoffice/branches/edit', 'backUrl' => '/backoffice/branches', 'branchId' => null])
        ->middleware('web_access:backoffice,admin|owner|superadmin,dash.overview');
    Route::get('/backoffice/branches/{branch}/edit', [BackofficePageController::class, 'branchEdit'])->middleware('web_access:backoffice,admin|owner|superadmin,dash.overview');
    Route::get('/backoffice/purchases/new', [TenantViewController::class, 'show'])->defaults('view', 'purchase-create')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,admin|owner|superadmin,dash.overview');
    Route::get('/backoffice/purchases/detail', [TenantViewController::class, 'show'])->defaults('view', 'purchase-detail')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,dash.overview');
    Route::get('/backoffice/purchases/returns', [TenantViewController::class, 'show'])->defaults('view', 'purchase-return')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,admin|owner|superadmin,dash.overview');
    Route::get('/backoffice/purchases/{purchase}', [BackofficePageController::class, 'purchaseDetail'])->middleware('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,dash.overview');
    Route::get('/backoffice/receiving', [BackofficePageController::class, 'receivingIndex'])->middleware('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,dash.overview');
    Route::get('/backoffice/receiving/action', [BackofficePageController::class, 'receivingActionRedirect'])
        ->middleware('web_access:backoffice,admin|owner|superadmin,dash.overview');
    Route::get('/backoffice/receiving/{purchase}', [BackofficePageController::class, 'receivingDetail'])->middleware('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,dash.overview');
    Route::get('/backoffice/receiving/{purchase}/action', [BackofficePageController::class, 'receivingAction'])->middleware('web_access:backoffice,admin|owner|superadmin,dash.overview');
    Route::get('/backoffice/reports/sales', [TenantViewController::class, 'show'])->defaults('view', 'sales-history')->defaults('data', ['showMenu' => true, 'historyContext' => 'backoffice'])->middleware('web_access:backoffice,supervisor|admin|owner|superadmin,reports.summary');
    Route::get('/backoffice/reports/inventory/stock-card', [TenantViewController::class, 'show'])->defaults('view', 'stock-card')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,reports.inventory_stock_card');
    Route::get('/backoffice/inventory/products/{product}/stock-card', [BackofficePageController::class, 'stockCardDetail'])->middleware('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,reports.inventory_stock_card');
    Route::get('/backoffice/reports/inventory/stock-alerts', [TenantViewController::class, 'show'])->defaults('view', 'stock-alerts')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,reports.inventory_view');
    Route::get('/backoffice/inventory/stock-adjustment', [TenantViewController::class, 'show'])->defaults('view', 'stock-adjustment')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,dash.overview');
    Route::get('/backoffice/inventory/stock-transfers', [TenantViewController::class, 'show'])->defaults('view', 'stock-transfers')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,dash.overview');
    Route::get('/backoffice/inventory/production-batches', [TenantViewController::class, 'show'])->defaults('view', 'production-batches')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,dash.overview');
    Route::get('/backoffice/inventory/production-batches/{batch}', [BackofficePageController::class, 'productionBatchDetail'])->middleware('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,dash.overview');
    Route::get('/backoffice/users', [TenantViewController::class, 'show'])->defaults('view', 'users')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,admin|owner|superadmin,dash.overview');
    Route::get('/backoffice/loyalty', [TenantViewController::class, 'show'])->defaults('view', 'loyalty')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,dash.overview');
    Route::get('/backoffice/settings/tax-promo', [TenantViewController::class, 'show'])->defaults('view', 'tax-promo')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,dash.overview');
    Route::get('/backoffice/settings/inventory-valuation', [TenantViewController::class, 'show'])->defaults('view', 'inventory-valuation-settings')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,admin|owner|superadmin,settings.inventory_valuation');
    Route::get('/backoffice/settings/inventory-valuation-audit', [TenantViewController::class, 'show'])->defaults('view', 'inventory-valuation-audit')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,admin|owner|superadmin,settings.inventory_valuation');
    Route::get('/backoffice/settings/refund-legacy-audit', [TenantViewController::class, 'show'])->defaults('view', 'refund-legacy-audit')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,admin|owner|superadmin,dash.overview');
    Route::get('/backoffice/settings/observability', [TenantViewController::class, 'show'])->defaults('view', 'observability')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,admin|owner|superadmin,settings.observability');
    Route::get('/backoffice/settings/chart-of-accounts', [TenantViewController::class, 'show'])->defaults('view', 'chart-of-accounts')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,admin|owner|superadmin,dash.overview');
    Route::get('/backoffice/settings/chart-of-accounts/{account}', [TenantViewController::class, 'showWithRouteParam'])
        ->defaults('view', 'chart-of-accounts-detail')
        ->defaults('routeParam', 'account')
        ->defaults('viewDataKey', 'accountId')
        ->defaults('data', ['showMenu' => true])
        ->middleware('web_access:backoffice,admin|owner|superadmin,dash.overview');
    Route::get('/backoffice/settings/bank-accounts/{account}', [TenantViewController::class, 'showWithRouteParam'])
        ->defaults('view', 'bank-account-detail')
        ->defaults('routeParam', 'account')
        ->defaults('viewDataKey', 'accountId')
        ->defaults('data', ['showMenu' => true])
        ->middleware('web_access:backoffice,admin|owner|superadmin,dash.overview');
    Route::get('/backoffice/finance/summary', [TenantViewController::class, 'show'])->defaults('view', 'finance-summary')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,dash.overview');
    Route::get('/backoffice/finance/lock-periods', [TenantViewController::class, 'show'])->defaults('view', 'lock-periods')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,dash.overview');
});

Route::get('/platform/dashboard', [PlatformDashboardController::class, 'index']);
