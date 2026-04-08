# PR Summary Web Routes Portal Rewrite 2026-04-08

## Scope

PR ini mengisolasi slimming `routes/web.php` dan pemindahan flow portal/web ke controller kecil agar file route kembali deklaratif dan lebih mudah direview. Batch ini fokus hanya ke surface web: portal auth flow, onboarding/menu extraction, tenant-aware simple views, legacy redirect wrappers, detail page flow POS/backoffice, dan platform dashboard web.

## Included Files

- `app/Http/Controllers/Web/PortalAuthController.php`
- `app/Http/Controllers/Web/PortalSessionController.php`
- `app/Http/Controllers/Web/OnboardingController.php`
- `app/Http/Controllers/Web/MenuController.php`
- `app/Http/Controllers/Web/TenantViewController.php`
- `app/Http/Controllers/Web/LegacyWebRedirectController.php`
- `app/Http/Controllers/Web/PosPageController.php`
- `app/Http/Controllers/Web/BackofficePageController.php`
- `app/Http/Controllers/Web/PlatformDashboardController.php`
- `app/Services/PortalSwitchService.php`
- `app/Services/WebRouteContextService.php`
- `routes/web.php`

## Related Validated Files

File berikut terkait langsung dengan behavior batch web ini dan tervalidasi di workspace saat ini, walau tidak semuanya harus ikut di diff PR kalau memang sudah tidak aktif terhadap HEAD:

- `tests/Feature/PortalWebAccessTest.php`
- `tests/Feature/WebOperationalPagesSmokeTest.php`
- `tests/Feature/PlatformSuperadminDangerActionsTest.php`
- `tests/Feature/WebAuthOnboardingSmokeTest.php`
- `tests/Feature/OnboardingFirstTimeFlowTest.php`

## What Changed

### Route Slimming

- Helper closure tenant/view di `routes/web.php` dipindah ke `WebRouteContextService`.
- Grouped simple view wiring POS/backoffice dipusatkan ke `TenantViewController`.
- Wrapper redirect ringan dipusatkan ke `LegacyWebRedirectController`.
- Sisa detail-page closure dipindah lagi ke `PosPageController` dan `BackofficePageController`.
- `/platform/dashboard` dipindah ke `PlatformDashboardController`.
- `routes/web.php` sekarang tinggal route wiring, middleware group, dan redirect deklaratif.

### Portal Flow

- Generic login, portal-specific login, logout, dan portal switch sekarang berada di controller/service yang lebih jelas batas tanggung jawabnya.
- Landing redirect portal tetap mempertahankan role-aware behavior untuk POS vs backoffice.
- Canonical redirect legacy wrapper tetap aman termasuk interpolasi placeholder target route.

### Detail View Flow

- POS checkout, sales detail, void/refund, receipt, payment method, dan shift summary pindah ke controller web khusus.
- Backoffice detail pages seperti product, purchase, receiving, branch edit, stock card, dan production batch detail juga dipindah dari route closure ke controller.
- Context `from`, `backUrl`, `currentPath`, dan owner shortcut tetap dipertahankan lewat `WebSalesFlowContext` dan `WebEntityFlowContext`.

### Platform Dashboard Web

- Superadmin gate dan impersonation expiry audit untuk `/platform/dashboard` tidak lagi hidup di route closure.
- Audit event `tenant_impersonation_ended` tetap dipertahankan di controller web baru.

## Validation

- `php artisan test tests/Feature/PortalWebAccessTest.php` sukses: `109` test pass.
- `php artisan test tests/Feature/WebOperationalPagesSmokeTest.php` sukses: `13` test pass.
- `php artisan test tests/Feature/PlatformSuperadminDangerActionsTest.php --filter=impersonation_expiry_logs_audit_end_event` sukses.

## Focused Diff

- Fokus diff seharusnya hanya file web controller/service dan `routes/web.php`.
- Saat validasi akhir, route file sudah tidak punya action closure lagi.

## Explicitly Excluded

PR ini tidak boleh otomatis membawa:

- ekspansi besar `routes/api.php`
- middleware feature/subscription/platform API baru
- command/console batch baru
- refund/subscription/platform API surface baru
- workflow deploy staging
- APK artifacts
- test fixture dan domain logic non-web yang sedang aktif di worktree

## Additional Note

- Terdapat fix kecil terpisah pada `app/Services/TenantDefaultRoleSetupService.php` untuk menghapus karakter liar sebelum tag `<?php` agar PHP parse normal kembali. Jika ingin slice PR web tetap sangat ketat, file ini sebaiknya diperlakukan sebagai hotfix terpisah.