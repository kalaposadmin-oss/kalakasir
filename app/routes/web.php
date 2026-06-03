<?php

use App\Http\Controllers\Web\BackofficePageController;
use App\Http\Controllers\Api\OpeningBalanceController;
use App\Http\Controllers\Web\OpeningBalancePageController;
use App\Http\Controllers\Web\PortalAuthController;
use App\Http\Controllers\Web\LegacyWebRedirectController;
use App\Http\Controllers\Web\CashInController;
use App\Http\Controllers\Web\CashOutController;
use App\Http\Controllers\Web\CashManagementController;
use App\Http\Controllers\Web\MenuController;
use App\Http\Controllers\Web\OnboardingController;
use App\Http\Controllers\Web\OrderOnlineController;
use App\Http\Controllers\Web\PlatformDashboardController;
use App\Http\Controllers\Web\PlatformResourcePageController;
use App\Http\Controllers\Web\PlatformSystemHealthPageController;
use App\Http\Controllers\Web\PosPageController;
use App\Http\Controllers\Web\PortalSessionController;
use App\Http\Controllers\Web\TenantViewController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PortalAuthController::class, 'showRoot']);
Route::post('/', [PortalAuthController::class, 'loginRoot']);
Route::get('/sample_products.csv', [\App\Http\Controllers\Web\ProductImportController::class, 'downloadTemplate']);

Route::get('/register', [OnboardingController::class, 'registerRedirect']);

Route::get('/register/step-1', [OnboardingController::class, 'showStepOne']);

Route::post('/register/step-1', [OnboardingController::class, 'storeStepOne']);

Route::get('/register/step-2', [OnboardingController::class, 'showStepTwo']);

Route::post('/register/step-2', [OnboardingController::class, 'storeStepTwo']);

Route::get('/register/step-3', [OnboardingController::class, 'showStepThree']);

Route::post('/register/step-3', [OnboardingController::class, 'storeStepThree']);

Route::get('/register/step-4', [OnboardingController::class, 'showStepFour']);

Route::post('/register/step-4', [OnboardingController::class, 'storeStepFour']);

Route::get('/register/review', [OnboardingController::class, 'showReview']);

Route::post('/register/complete', [OnboardingController::class, 'completeReview']);

Route::middleware(['auth', 'canonical_portal_host:backoffice', 'web_access:backoffice,admin|owner|superadmin,finance.bank_accounts'])->group(function () {
    Route::get('/admin/opening-balance/current', [OpeningBalanceController::class, 'currentDraft']);
    Route::get('/admin/opening-balance/history', [OpeningBalanceController::class, 'history']);
    Route::post('/admin/opening-balance', [OpeningBalanceController::class, 'createDraft']);
    Route::post('/admin/opening-balance/{session}/stock', [OpeningBalanceController::class, 'addStock']);
    Route::patch('/admin/opening-balance/{session}/stock/{line}', [OpeningBalanceController::class, 'updateStock']);
    Route::delete('/admin/opening-balance/{session}/stock/{line}', [OpeningBalanceController::class, 'deleteStock']);
    Route::post('/admin/opening-balance/{session}/fund', [OpeningBalanceController::class, 'addFund']);
    Route::patch('/admin/opening-balance/{session}/fund/{line}', [OpeningBalanceController::class, 'updateFund']);
    Route::delete('/admin/opening-balance/{session}/fund/{line}', [OpeningBalanceController::class, 'deleteFund']);
    Route::post('/admin/opening-balance/validate', [OpeningBalanceController::class, 'validateDraft']);
    Route::post('/admin/opening-balance/post', [OpeningBalanceController::class, 'post']);
});

Route::middleware(['canonical_portal_host:backoffice', 'web_access:backoffice,admin|owner|superadmin,finance.bank_accounts'])->group(function () {
    Route::get('/backoffice/settings/payment-settings', [BackofficePageController::class, 'paymentSettings']);
    Route::post('/backoffice/settings/payment-settings', [BackofficePageController::class, 'savePaymentSettings']);
});

Route::middleware(['canonical_portal_host:backoffice', 'web_access:backoffice,admin|owner|superadmin,finance.bank_accounts'])->group(function () {
    Route::get('/admin/opening-balance', [OpeningBalancePageController::class, 'index']);
});

Route::get('/menu', [MenuController::class, 'index']);

Route::get('/menu/{section}', [MenuController::class, 'section']);

Route::get('/inventory', [LegacyWebRedirectController::class, 'to'])->defaults('target', '/backoffice/inventory');

Route::get('/reports/inventory', [LegacyWebRedirectController::class, 'to'])->defaults('target', '/backoffice/reports/inventory');

Route::get('/stock-card', [LegacyWebRedirectController::class, 'stockCard']);

Route::get('/settings/chart-of-accounts', [LegacyWebRedirectController::class, 'to'])->defaults('target', '/backoffice/settings/chart-of-accounts');

Route::get('/settings/payment-settings', [LegacyWebRedirectController::class, 'to'])->defaults('target', '/backoffice/settings/payment-settings');

Route::get('/settings/chart-of-accounts/{account}', [LegacyWebRedirectController::class, 'to'])
    ->defaults('target', '/backoffice/settings/chart-of-accounts/{account}');

Route::get('/settings/bank-accounts/{account}', [LegacyWebRedirectController::class, 'to'])
    ->defaults('target', '/backoffice/settings/bank-accounts/{account}');

Route::get('/stock-adjustment', [LegacyWebRedirectController::class, 'to'])->defaults('target', '/backoffice/inventory/stock-adjustment');

Route::get('/stock-opname', [LegacyWebRedirectController::class, 'to'])->defaults('target', '/backoffice/inventory/stock-opname');

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

Route::get('/receivables', [LegacyWebRedirectController::class, 'to'])->defaults('target', '/backoffice/reports/receivables');

Route::get('/lock-periods', [LegacyWebRedirectController::class, 'to'])->defaults('target', '/backoffice/finance/lock-periods');

Route::get('/stock-transfers', [LegacyWebRedirectController::class, 'to'])->defaults('target', '/backoffice/inventory/stock-transfers');

Route::get('/production-batches', [LegacyWebRedirectController::class, 'to'])->defaults('target', '/backoffice/inventory/production-batches');

Route::get('/production-batches/{batch}', [LegacyWebRedirectController::class, 'to'])
    ->defaults('target', '/backoffice/inventory/production-batches/{batch}');

Route::get('/loyalty', [LegacyWebRedirectController::class, 'to'])->defaults('target', '/backoffice/loyalty');

Route::get('/backoffice', [PortalAuthController::class, 'showBackofficeEntry']);
Route::post('/backoffice', [PortalAuthController::class, 'loginBackoffice']);

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
    Route::get('/pos', fn () => redirect(pos_default_url()));
    Route::get('/pos/dashboard', [TenantViewController::class, 'show'])
        ->defaults('view', 'pos-dashboard')
        ->defaults('data', ['showMenu' => true]);
  });

Route::middleware(['canonical_portal_host:pos', 'web_access:pos,cashier|staff|warehouse|supervisor|admin|owner|superadmin,finance.cash_transactions'])->group(function () {
    // Unified Kas management page
    Route::get('/pos/cash-in', [CashManagementController::class, 'index']);
    Route::get('/pos/cash-out', function () { return redirect('/pos/cash-in'); });
    Route::get('/pos/cash-in/create', function () { return redirect('/pos/cash-in'); });
    Route::get('/pos/cash-out/create', function () { return redirect('/pos/cash-in'); });

    // Store actions
    Route::post('/pos/cash-in', [CashManagementController::class, 'storeCashIn']);
    Route::post('/pos/cash-out', [CashManagementController::class, 'storeCashOut']);
});

Route::middleware(['canonical_portal_host:pos'])->group(function () {
    Route::get('/pos/sales/{sale}', [PosPageController::class, 'saleDetail'])
        ->middleware('web_access:pos,cashier|staff|warehouse|supervisor|admin|owner|superadmin,history.sales');
    Route::get('/pos/sales/{sale}/receipt', [PosPageController::class, 'saleReceipt'])
        ->middleware('web_access:pos,cashier|staff|warehouse|supervisor|admin|owner|superadmin,pos.checkout');
    Route::get('/pos/payment-method', [PosPageController::class, 'paymentMethod'])
        ->middleware('web_access:pos,cashier|staff|warehouse|supervisor|admin|owner|superadmin,pos.checkout');
    Route::get('/pos/receipt-preview', [PosPageController::class, 'receiptPreview'])
        ->middleware('web_access:pos,cashier|staff|warehouse|supervisor|admin|owner|superadmin,pos.checkout');
    Route::get('/pos/sales/{sale}/void-refund', [PosPageController::class, 'saleVoidRefund'])
        ->middleware('web_access:pos,cashier|staff|warehouse|supervisor|admin|owner|superadmin,pos.void');
    Route::get('/pos/void-refund', [TenantViewController::class, 'show'])
        ->defaults('view', 'void-refund')
        ->defaults('data', ['showMenu' => true])
        ->middleware('web_access:pos,cashier|staff|warehouse|supervisor|admin|owner|superadmin,pos.void');
    Route::get('/pos/checkout', [PosPageController::class, 'checkout'])
        ->middleware(['web_access:pos,cashier|staff|warehouse|supervisor|admin|owner|superadmin,pos.checkout', 'shift_open']);
    Route::get('/pos/history', [TenantViewController::class, 'show'])
        ->defaults('view', 'sales-history')
        ->defaults('data', ['showMenu' => true, 'historyContext' => 'pos'])
        ->middleware('web_access:pos,cashier|staff|warehouse|supervisor|admin|owner|superadmin,history.sales');
    Route::get('/pos/receivables', [TenantViewController::class, 'show'])
        ->defaults('view', 'receivables')
        ->defaults('data', ['showMenu' => true, 'receivableContext' => 'pos'])
        ->middleware('web_access:pos,cashier|staff|warehouse|supervisor|admin|owner|superadmin,history.sales');
    Route::get('/pos/customers', [TenantViewController::class, 'show'])
        ->defaults('view', 'customers')
        ->defaults('data', ['showMenu' => true, 'backUrl' => '/pos/dashboard'])
        ->middleware('web_access:pos,warehouse|supervisor|admin|owner|superadmin,customer.manage');
    Route::get('/pos/shifts/open', [TenantViewController::class, 'show'])
        ->defaults('view', 'shift-open')
        ->defaults('data', ['showMenu' => true])
        ->middleware('web_access:pos,cashier|staff|warehouse|supervisor|admin|owner|superadmin,shift.open');
    Route::get('/pos/shifts/close', [TenantViewController::class, 'show'])
        ->defaults('view', 'shift-close')
        ->defaults('data', ['showMenu' => true])
        ->middleware('web_access:pos,cashier|staff|warehouse|supervisor|admin|owner|superadmin,shift.close');
    Route::get('/pos/shifts/summary', [PosPageController::class, 'shiftSummaryIndex'])
        ->middleware('web_access:pos,cashier|staff|warehouse|supervisor|admin|owner|superadmin,shift.history');
    Route::get('/pos/shifts/{shift}/summary', [PosPageController::class, 'shiftSummaryDetail'])
        ->middleware('web_access:pos,cashier|staff|warehouse|supervisor|admin|owner|superadmin,shift.history');
    Route::get('/pos/products', [TenantViewController::class, 'show'])
        ->defaults('view', 'products')
        ->defaults('data', ['showMenu' => true])
        ->middleware('web_access:pos,warehouse|supervisor|admin|owner|superadmin,product.list');
    Route::get('/pos/products/new', [TenantViewController::class, 'show'])
        ->defaults('view', 'product-create')
        ->defaults('data', ['showMenu' => true])
        ->middleware('web_access:pos,admin|owner|superadmin,product.list');
    Route::get('/pos/products/{product}', [PosPageController::class, 'productDetail'])
        ->middleware('web_access:pos,warehouse|supervisor|admin|owner|superadmin,product.list');
    Route::get('/pos/products/{product}/edit', [TenantViewController::class, 'showWithRouteParam'])
        ->defaults('view', 'product-edit')
        ->defaults('routeParam', 'product')
        ->defaults('viewDataKey', 'productId')
        ->defaults('data', ['showMenu' => true])
        ->middleware('web_access:pos,admin|owner|superadmin,product.list');
    Route::get('/pos/materials', [TenantViewController::class, 'show'])
        ->defaults('view', 'materials')
        ->defaults('data', ['showMenu' => true])
        ->middleware('web_access:pos,admin|owner|superadmin,product.materials');
    Route::get('/pos/categories', [TenantViewController::class, 'show'])
        ->defaults('view', 'categories')
        ->defaults('data', ['showMenu' => true])
        ->middleware('web_access:pos,warehouse|supervisor|admin|owner|superadmin,product.category');
    Route::get('/pos/categories/new', [TenantViewController::class, 'show'])
        ->defaults('view', 'category-create')
        ->defaults('data', ['showMenu' => true])
        ->middleware('web_access:pos,admin|owner|superadmin,product.category');
    Route::get('/pos/categories/{category}/edit', [TenantViewController::class, 'showWithRouteParam'])
        ->defaults('view', 'category-edit')
        ->defaults('routeParam', 'category')
        ->defaults('viewDataKey', 'categoryId')
        ->defaults('data', ['showMenu' => true])
        ->middleware('web_access:pos,admin|owner|superadmin,product.category');
    Route::get('/pos/purchases', [TenantViewController::class, 'show'])
        ->defaults('view', 'purchase-index')
        ->defaults('data', ['showMenu' => true])
        ->middleware('web_access:pos,warehouse|supervisor|admin|owner|superadmin,history.purchases');
    Route::get('/pos/purchases/new', [TenantViewController::class, 'show'])
        ->defaults('view', 'purchase-create')
        ->defaults('data', ['showMenu' => true])
        ->middleware('web_access:pos,warehouse|supervisor|admin|owner|superadmin,purchase.order');
    Route::get('/pos/purchases/returns', [TenantViewController::class, 'show'])
        ->defaults('view', 'purchase-return')
        ->defaults('data', ['showMenu' => true])
        ->middleware('web_access:pos,admin|owner|superadmin,purchase.return');
    Route::get('/pos/purchases/{purchase}', [LegacyWebRedirectController::class, 'to'])
        ->defaults('target', '/pos/receiving/{purchase}')
        ->middleware('web_access:pos,warehouse|supervisor|admin|owner|superadmin,purchase.receive');
    Route::get('/pos/receiving', [TenantViewController::class, 'show'])
        ->defaults('view', 'receiving')
        ->defaults('data', ['showMenu' => true])
        ->middleware('web_access:pos,warehouse|supervisor|admin|owner|superadmin,purchase.receive');
    Route::get('/pos/receiving/{purchase}', [BackofficePageController::class, 'receivingDetail'])
        ->middleware('web_access:pos,warehouse|supervisor|admin|owner|superadmin,purchase.receive');
    Route::get('/pos/receiving/{purchase}/action', [BackofficePageController::class, 'receivingAction'])
        ->middleware('web_access:pos,admin|owner|superadmin,purchase.receive');
    Route::get('/pos/branches', [TenantViewController::class, 'show'])
        ->defaults('view', 'branches')
        ->defaults('data', ['showMenu' => true])
        ->middleware('web_access:pos,admin|owner|superadmin,settings.branches_manage');
    Route::get('/pos/finance/summary', [TenantViewController::class, 'show'])
        ->defaults('view', 'finance-summary')
        ->defaults('data', ['showMenu' => true, 'backUrl' => '/pos/dashboard'])
        ->middleware('web_access:pos,warehouse|supervisor|admin|owner|superadmin,finance.summary_basic');
    Route::get('/pos/users', [TenantViewController::class, 'show'])
        ->defaults('view', 'users')
        ->defaults('data', ['showMenu' => true])
        ->middleware('web_access:pos,admin|owner|superadmin,settings.staff_basic');
    Route::get('/pos/inventory', [TenantViewController::class, 'show'])
        ->defaults('view', 'inventory')
        ->defaults('data', ['showMenu' => true])
        ->middleware('web_access:pos,warehouse|supervisor|admin|owner|superadmin,inventory.stock');
    Route::get('/pos/inventory/stock-card', [TenantViewController::class, 'show'])
        ->defaults('view', 'stock-card')
        ->defaults('data', ['showMenu' => true])
        ->middleware('web_access:pos,warehouse|supervisor|admin|owner|superadmin,reports.inventory_stock_card');
    Route::get('/pos/inventory/products/{product}/stock-card', [TenantViewController::class, 'showWithRouteParam'])
        ->defaults('view', 'stock-card')
        ->defaults('routeParam', 'product')
        ->defaults('viewDataKey', 'productId')
        ->defaults('data', ['showMenu' => true])
        ->middleware('web_access:pos,warehouse|supervisor|admin|owner|superadmin,reports.inventory_stock_card');
    Route::get('/pos/inventory/stock-adjustment', [TenantViewController::class, 'show'])
        ->defaults('view', 'stock-adjustment')
        ->defaults('data', ['showMenu' => true])
        ->middleware('web_access:pos,warehouse|supervisor|admin|owner|superadmin,inventory.adjustment');
    Route::get('/pos/inventory/stock-opname', [TenantViewController::class, 'show'])
        ->defaults('view', 'stock-opname')
        ->defaults('data', ['showMenu' => true])
        ->middleware('web_access:pos,warehouse|supervisor|admin|owner|superadmin,inventory.stock_opname');
    Route::get('/pos/inventory/production-batches', [TenantViewController::class, 'show'])
        ->defaults('view', 'production-batches')
        ->defaults('data', ['showMenu' => true])
        ->middleware('web_access:pos,warehouse|supervisor|admin|owner|superadmin,inventory.production_batches');
    Route::get('/pos/inventory/production-batches/{batch}', [TenantViewController::class, 'showWithRouteParam'])
        ->defaults('view', 'production-batch-detail')
        ->defaults('routeParam', 'batch')
        ->defaults('viewDataKey', 'batchId')
        ->defaults('data', ['showMenu' => true])
        ->middleware('web_access:pos,warehouse|supervisor|admin|owner|superadmin,inventory.production_batches');
});

Route::middleware(['canonical_portal_host:backoffice', 'web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,dash.overview'])->group(function () {
    Route::get('/backoffice/dashboard', [TenantViewController::class, 'show'])
        ->defaults('view', 'backoffice-dashboard')
        ->defaults('data', ['showMenu' => true]);
    Route::get('/backoffice/marketing/online-store', [TenantViewController::class, 'show'])->defaults('view', 'marketing-online-store')->defaults('data', ['showMenu' => true]);
    Route::get('/backoffice/marketing/referral', [TenantViewController::class, 'show'])->defaults('view', 'marketing-referral')->defaults('data', ['showMenu' => true]);
});

Route::middleware(['canonical_portal_host:backoffice'])->group(function () {
    Route::get('/backoffice/products', [TenantViewController::class, 'show'])->defaults('view', 'products')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,product.list');
    Route::get('/backoffice/products/new', [TenantViewController::class, 'show'])->defaults('view', 'product-create')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,admin|owner|superadmin,product.list');

    // Product import (CSV)
    Route::get('/backoffice/products/import', [\App\Http\Controllers\Web\ProductImportController::class, 'showImportForm'])->middleware('web_access:backoffice,admin|owner|superadmin,product.list');
    Route::get('/backoffice/products/import/template', [\App\Http\Controllers\Web\ProductImportController::class, 'downloadTemplate'])->middleware('web_access:backoffice,admin|owner|superadmin,product.list');
    Route::post('/backoffice/products/import', [\App\Http\Controllers\Web\ProductImportController::class, 'handleImport'])->middleware('web_access:backoffice,admin|owner|superadmin,product.list');
    Route::post('/backoffice/products/import/process', [\App\Http\Controllers\Web\ProductImportController::class, 'processImport'])->middleware('web_access:backoffice,admin|owner|superadmin,product.list');

    Route::get('/backoffice/products/detail', [TenantViewController::class, 'show'])->defaults('view', 'product-detail')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,product.list');
    Route::get('/backoffice/products/{product}', [BackofficePageController::class, 'productDetail'])->middleware('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,product.list');
    Route::get('/backoffice/products/{product}/edit', [TenantViewController::class, 'showWithRouteParam'])
        ->defaults('view', 'product-edit')
        ->defaults('routeParam', 'product')
        ->defaults('viewDataKey', 'productId')
        ->defaults('data', ['showMenu' => true])
        ->middleware('web_access:backoffice,admin|owner|superadmin,product.list');
    Route::get('/backoffice/categories', [TenantViewController::class, 'show'])->defaults('view', 'categories')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,product.category');
    Route::get('/backoffice/categories/new', [TenantViewController::class, 'show'])->defaults('view', 'category-create')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,admin|owner|superadmin,product.category');
    Route::get('/backoffice/categories/{category}/edit', [TenantViewController::class, 'showWithRouteParam'])
        ->defaults('view', 'category-edit')
        ->defaults('routeParam', 'category')
        ->defaults('viewDataKey', 'categoryId')
        ->defaults('data', ['showMenu' => true])
        ->middleware('web_access:backoffice,admin|owner|superadmin,product.category');
    Route::get('/backoffice/inventory', [TenantViewController::class, 'show'])->defaults('view', 'inventory')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,inventory.stock');
    Route::get('/backoffice/materials', [TenantViewController::class, 'show'])->defaults('view', 'materials')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,admin|owner|superadmin,product.materials');
    Route::get('/backoffice/customers', [TenantViewController::class, 'show'])->defaults('view', 'customers')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,customer.manage');
    Route::get('/backoffice/suppliers', [TenantViewController::class, 'show'])->defaults('view', 'suppliers')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,supplier.manage');
    Route::get('/backoffice/branches', [TenantViewController::class, 'show'])->defaults('view', 'branches')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,settings.branches_manage');
    Route::get('/backoffice/branches/edit', [TenantViewController::class, 'show'])
        ->defaults('view', 'branch-edit')
        ->defaults('data', ['showMenu' => true, 'currentPath' => '/backoffice/branches/edit', 'backUrl' => '/backoffice/branches', 'branchId' => null])
        ->middleware('web_access:backoffice,admin|owner|superadmin,settings.branches_manage');
    Route::get('/backoffice/branches/{branch}/edit', [BackofficePageController::class, 'branchEdit'])->middleware('web_access:backoffice,admin|owner|superadmin,settings.branches_manage');
    Route::get('/backoffice/purchases', [TenantViewController::class, 'show'])->defaults('view', 'purchase-index')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,history.purchases');
    Route::get('/backoffice/purchases/new', [TenantViewController::class, 'show'])->defaults('view', 'purchase-create')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,purchase.order');
    Route::get('/backoffice/purchases/detail', [TenantViewController::class, 'show'])->defaults('view', 'purchase-detail')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,history.purchases');
    Route::get('/backoffice/purchases/returns', [TenantViewController::class, 'show'])->defaults('view', 'purchase-return')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,admin|owner|superadmin,purchase.return');
    Route::get('/backoffice/purchases/{purchase}', [BackofficePageController::class, 'purchaseDetail'])->middleware('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,history.purchases');
    Route::get('/backoffice/receiving', [BackofficePageController::class, 'receivingIndex'])->middleware('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,purchase.receive');
    Route::get('/backoffice/receiving/action', [BackofficePageController::class, 'receivingActionRedirect'])
        ->middleware('web_access:backoffice,admin|owner|superadmin,purchase.receive');
    Route::get('/backoffice/receiving/{purchase}', [BackofficePageController::class, 'receivingDetail'])->middleware('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,purchase.receive');
    Route::get('/backoffice/receiving/{purchase}/action', [BackofficePageController::class, 'receivingAction'])->middleware('web_access:backoffice,admin|owner|superadmin,purchase.receive');
    Route::get('/backoffice/inventory/stock-adjustment', [TenantViewController::class, 'show'])->defaults('view', 'stock-adjustment')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,inventory.adjustment');
    Route::get('/backoffice/inventory/stock-opname', [TenantViewController::class, 'show'])->defaults('view', 'stock-opname')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,inventory.stock_opname');
    Route::get('/backoffice/inventory/fixed-assets', [TenantViewController::class, 'show'])->defaults('view', 'fixed-assets')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,inventory.stock');
    Route::get('/backoffice/inventory/stock-transfers', [TenantViewController::class, 'show'])->defaults('view', 'stock-transfers')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,inventory.transfer');
    Route::get('/backoffice/inventory/production-batches', [TenantViewController::class, 'show'])->defaults('view', 'production-batches')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,inventory.production_batches');
    Route::get('/backoffice/inventory/production-batches/{batch}', [BackofficePageController::class, 'productionBatchDetail'])->middleware('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,inventory.production_batches');
    Route::get('/backoffice/users', [TenantViewController::class, 'show'])->defaults('view', 'users')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,admin|owner|superadmin,settings.staff_basic');
    Route::get('/backoffice/loyalty', [TenantViewController::class, 'show'])->defaults('view', 'loyalty')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,loyalty.program');
    Route::get('/backoffice/settings/tax-promo', [TenantViewController::class, 'show'])->defaults('view', 'tax-promo')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,settings.tax');
    Route::get('/backoffice/settings/inventory-method', [TenantViewController::class, 'show'])->defaults('view', 'inventory-method-basic')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,admin|owner|superadmin,settings.inventory_method_basic');
    Route::get('/backoffice/settings/inventory-valuation', [TenantViewController::class, 'show'])->defaults('view', 'inventory-valuation-settings')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,admin|owner|superadmin,settings.inventory_valuation');
    Route::get('/backoffice/settings/inventory-valuation-audit', [TenantViewController::class, 'show'])->defaults('view', 'inventory-valuation-audit')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,admin|owner|superadmin,settings.inventory_valuation');
    Route::get('/backoffice/sales/{sale}', [BackofficePageController::class, 'saleDetail'])->middleware('web_access:backoffice,supervisor|admin|owner|superadmin,reports.summary');

    // Order Online System - Levia Bakery
    Route::get('/backoffice/online-orders', [TenantViewController::class, 'show'])->defaults('view', 'online-orders')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,supervisor|admin|owner|superadmin,sale.pos');
    Route::get('/backoffice/online-orders/{order}', [BackofficePageController::class, 'onlineOrderDetail'])->middleware('web_access:backoffice,supervisor|admin|owner|superadmin,sale.pos');
    Route::get('/backoffice/online-orders/{order}/process', [BackofficePageController::class, 'onlineOrderProcess'])->middleware('web_access:backoffice,supervisor|admin|owner|superadmin,sale.pos');

    Route::get('/backoffice/reports', [TenantViewController::class, 'show'])->defaults('view', 'backoffice-reports')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,supervisor|admin|owner|superadmin,reports.summary');
    Route::get('/backoffice/reports/overview', [TenantViewController::class, 'show'])->defaults('view', 'backoffice-reports')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,supervisor|admin|owner|superadmin,reports.summary');
    Route::get('/backoffice/reports/purchases', [TenantViewController::class, 'show'])->defaults('view', 'reports-purchases')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,supervisor|admin|owner|superadmin,reports.purchases_view');
    Route::get('/backoffice/reports/inventory', [TenantViewController::class, 'show'])->defaults('view', 'reports-inventory')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,reports.inventory_view');
    Route::get('/backoffice/reports/sales', [TenantViewController::class, 'show'])->defaults('view', 'sales-history')->defaults('data', ['showMenu' => true, 'historyContext' => 'backoffice'])->middleware('web_access:backoffice,supervisor|admin|owner|superadmin,reports.summary');
    Route::get('/backoffice/reports/receivables', [TenantViewController::class, 'show'])->defaults('view', 'receivables')->defaults('data', ['showMenu' => true, 'receivableContext' => 'backoffice'])->middleware('web_access:backoffice,supervisor|admin|owner|superadmin,reports.summary');
    Route::get('/backoffice/reports/inventory/stock-card', [TenantViewController::class, 'show'])->defaults('view', 'stock-card')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,reports.inventory_stock_card');
    Route::get('/backoffice/inventory/stock-card', [TenantViewController::class, 'show'])->defaults('view', 'stock-card')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,reports.inventory_stock_card');
    Route::get('/backoffice/inventory/products/{product}/stock-card', [BackofficePageController::class, 'stockCardDetail'])->middleware('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,reports.inventory_stock_card');
    Route::get('/backoffice/reports/inventory/stock-alerts', [TenantViewController::class, 'show'])->defaults('view', 'stock-alerts')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,reports.inventory_view');
    Route::get('/backoffice/settings/subscription', [TenantViewController::class, 'show'])->defaults('view', 'subscription-current')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,settings.subscription_billing');
    Route::get('/backoffice/settings/subscription/history', [TenantViewController::class, 'show'])->defaults('view', 'subscription-history')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,settings.subscription_billing');
    Route::get('/backoffice/settings/subscription/invoices', [TenantViewController::class, 'show'])->defaults('view', 'subscription-invoices')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,settings.subscription_billing');
    Route::get('/backoffice/settings/subscription/change', [TenantViewController::class, 'show'])->defaults('view', 'subscription-change')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,admin|owner|superadmin,settings.subscription_billing');
    Route::get('/backoffice/settings/subscription/features', [TenantViewController::class, 'show'])->defaults('view', 'subscription-features')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,settings.subscription_billing');
    Route::get('/backoffice/settings/integrations', [TenantViewController::class, 'show'])->defaults('view', 'settings-integrations')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,settings.integrations');
    Route::get('/backoffice/settings/preferences', [TenantViewController::class, 'show'])->defaults('view', 'settings-preferences')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,settings.store_profile');
    Route::post('/backoffice/settings/preferences', [BackofficePageController::class, 'savePreferences'])->middleware('web_access:backoffice,admin|owner|superadmin,settings.store_profile');
    Route::get('/backoffice/settings/refund-legacy-audit', [TenantViewController::class, 'show'])
        ->defaults('view', 'refund-legacy-audit')
        ->defaults('data', ['showMenu' => true])
        ->middleware('web_access:backoffice,admin|owner|superadmin');
    Route::get('/backoffice/settings/observability', [TenantViewController::class, 'show'])->defaults('view', 'observability')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,admin|owner|superadmin,settings.observability');
    Route::get('/backoffice/settings/chart-of-accounts', [TenantViewController::class, 'show'])->defaults('view', 'chart-of-accounts')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,admin|owner|superadmin,finance.bank_accounts');
    Route::get('/backoffice/finance/payment-settlements', [TenantViewController::class, 'show'])->defaults('view', 'payment-settlements')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,admin|owner|superadmin,finance.bank_accounts');
    Route::get('/backoffice/finance/cash', [CashManagementController::class, 'index'])->middleware('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,finance.cash_transactions');
    Route::get('/backoffice/finance/cash/out', fn () => redirect('/backoffice/finance/cash'))->middleware('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,finance.cash_transactions');
    Route::post('/backoffice/finance/cash', [CashManagementController::class, 'storeCashIn'])->middleware('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,finance.cash_transactions');
    Route::post('/backoffice/finance/cash/out', [CashManagementController::class, 'storeCashOut'])->middleware('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,finance.cash_transactions');
    Route::get('/backoffice/settings/chart-of-accounts/{account}', [TenantViewController::class, 'showWithRouteParam'])
        ->defaults('view', 'chart-of-accounts-detail')
        ->defaults('routeParam', 'account')
        ->defaults('viewDataKey', 'accountId')
        ->defaults('data', ['showMenu' => true])
        ->middleware('web_access:backoffice,admin|owner|superadmin,finance.bank_accounts');
    Route::get('/backoffice/settings/bank-accounts/{account}', [TenantViewController::class, 'showWithRouteParam'])
        ->defaults('view', 'bank-account-detail')
        ->defaults('routeParam', 'account')
        ->defaults('viewDataKey', 'accountId')
        ->defaults('data', ['showMenu' => true])
        ->middleware('web_access:backoffice,admin|owner|superadmin,finance.bank_accounts');
    Route::get('/backoffice/finance/summary', [TenantViewController::class, 'show'])->defaults('view', 'finance-summary')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,finance.summary_basic');
    Route::get('/backoffice/finance/lock-periods', [TenantViewController::class, 'show'])->defaults('view', 'lock-periods')->defaults('data', ['showMenu' => true])->middleware('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,finance.lock_period');
    Route::get('/backoffice/help/center', [TenantViewController::class, 'show'])
        ->defaults('view', 'help-center')
        ->defaults('data', ['showMenu' => true])
        ->middleware('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,help.center');
    Route::get('/backoffice/help/about', [TenantViewController::class, 'show'])
        ->defaults('view', 'help-about')
        ->defaults('data', ['showMenu' => true])
        ->middleware('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,help.center');
    Route::get('/backoffice/help/support', [TenantViewController::class, 'show'])
        ->defaults('view', 'help-support')
        ->defaults('data', ['showMenu' => true])
        ->middleware('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,help.center');
});

// Order Online - Public Facing Routes (Levia Bakery)
Route::get('/order', [OrderOnlineController::class, 'catalog']);
Route::get('/order/review', [OrderOnlineController::class, 'review'])->name('order.online.review');
Route::get('/order/start/{customer}', [OrderOnlineController::class, 'startForCustomer'])->name('order.online.customer-start');
Route::get('/order/reset-customer', [OrderOnlineController::class, 'resetCustomer']);
Route::post('/order/submit', [OrderOnlineController::class, 'submit']);
Route::get('/order/success/{orderCode}', [OrderOnlineController::class, 'success']);
Route::get('/admin-order/{code}', [OrderOnlineController::class, 'adminPin']);
Route::get('/admin-order/{code}/pin', [OrderOnlineController::class, 'adminPin']);
Route::post('/admin-order/{code}/pin', [OrderOnlineController::class, 'verifyAdminPin']);
Route::post('/admin-order/{code}/override', [OrderOnlineController::class, 'verifyCompletedOrderOverride']);
Route::get('/admin-order/{code}/edit', [OrderOnlineController::class, 'adminEdit']);
Route::post('/admin-order/{code}/edit', [OrderOnlineController::class, 'saveAdminEdit']);
Route::get('/admin-order/{code}/shipping', [OrderOnlineController::class, 'adminShipping']);
Route::post('/admin-order/{code}/shipping', [OrderOnlineController::class, 'saveShipping']);
Route::get('/admin-order/{code}/final-review', [OrderOnlineController::class, 'adminFinalReview']);
Route::post('/admin-order/{code}/final-review/send-payment-message', [OrderOnlineController::class, 'sendPaymentMessage']);
Route::post('/admin-order/{code}/mark-paid', [OrderOnlineController::class, 'markPaid']);
Route::post('/admin-order/{code}/complete', [OrderOnlineController::class, 'complete']);
Route::post('/admin-order/{code}/cancel', [OrderOnlineController::class, 'cancel']);
Route::get('/order/monitor', [OrderOnlineController::class, 'monitor']);
Route::get('/order/{customerLink}', [OrderOnlineController::class, 'startForCustomerLink'])
    ->where('customerLink', '[A-Za-z0-9-]+')
    ->name('order.online.customer-short-start');
Route::get('/monitor/orders', [OrderOnlineController::class, 'monitor']);
Route::get('/monitor/orders/data', [OrderOnlineController::class, 'monitorData']);

Route::get('/superadmin', fn () => redirect('/superadmin/dashboard'));
Route::get('/superadmin/dashboard', [PlatformDashboardController::class, 'index']);
Route::get('/superadmin/health', [PlatformResourcePageController::class, 'health']);
Route::get('/superadmin/tenants', [PlatformResourcePageController::class, 'tenants']);
Route::get('/superadmin/tenants/{tenantId}', [PlatformResourcePageController::class, 'tenantDetail']);
Route::get('/superadmin/users', [PlatformResourcePageController::class, 'users']);
Route::get('/superadmin/users/{userId}', [PlatformResourcePageController::class, 'userDetail']);
Route::get('/superadmin/billing', fn () => redirect('/superadmin/billing/invoices'));
Route::get('/superadmin/billing/invoices', [PlatformResourcePageController::class, 'invoices']);
Route::get('/superadmin/audit-logs', [PlatformResourcePageController::class, 'auditLogs']);
Route::get('/superadmin/anomalies', [PlatformResourcePageController::class, 'anomalies']);
Route::get('/superadmin/support/search', [PlatformResourcePageController::class, 'supportSearch']);
Route::get('/superadmin/system-health', [PlatformSystemHealthPageController::class, 'dashboard']);
Route::get('/superadmin/system-health/incidents', [PlatformSystemHealthPageController::class, 'incidents']);
Route::get('/superadmin/system-health/incidents/{eventId}', [PlatformSystemHealthPageController::class, 'show']);
Route::get('/superadmin/system-health/tenants', [PlatformSystemHealthPageController::class, 'tenants']);

Route::get('/platform/dashboard', [PlatformDashboardController::class, 'index']);
Route::get('/platform/health', [PlatformResourcePageController::class, 'health']);
Route::get('/platform/tenants', [PlatformResourcePageController::class, 'tenants']);
Route::get('/platform/tenants/{tenantId}', [PlatformResourcePageController::class, 'tenantDetail']);
Route::get('/platform/users', [PlatformResourcePageController::class, 'users']);
Route::get('/platform/users/{userId}', [PlatformResourcePageController::class, 'userDetail']);
Route::get('/platform/billing', fn () => redirect('/platform/billing/invoices'));
Route::get('/platform/billing/invoices', [PlatformResourcePageController::class, 'invoices']);
Route::get('/platform/audit-logs', [PlatformResourcePageController::class, 'auditLogs']);
Route::get('/platform/anomalies', [PlatformResourcePageController::class, 'anomalies']);
Route::get('/platform/support/search', [PlatformResourcePageController::class, 'supportSearch']);
Route::get('/platform/system-health', [PlatformSystemHealthPageController::class, 'dashboard']);
Route::get('/platform/system-health/incidents', [PlatformSystemHealthPageController::class, 'incidents']);
Route::get('/platform/system-health/incidents/{eventId}', [PlatformSystemHealthPageController::class, 'show']);
Route::get('/platform/system-health/tenants', [PlatformSystemHealthPageController::class, 'tenants']);
