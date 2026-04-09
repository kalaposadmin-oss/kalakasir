# Pending PR Backlog 2026-04-08

Dokumen ini menyimpan PR yang belum dikerjakan/ditutup agar bisa dilanjutkan pada sesi berikutnya tanpa mengulang analisis dari awal.

## 1. PR Audit Final

Status:

- sudah di-commit terpisah
- commit: `2b20228` `feat(api): add final observability headers for bootstrap and finance flows`

Active files terakhir yang masih muncul di diff:

- `app/Http/Controllers/Api/BootstrapController.php`
- `app/Http/Controllers/Api/CheckoutController.php`
- `app/Http/Controllers/Api/FinanceControlDashboardController.php`

Dokumen referensi:

- `docs/audit-final-batch-2026-04-08.md`
- `docs/pr-summary-audit-final-batch-2026-04-08.md`
- `docs/commit-message-audit-final-batch-2026-04-08.md`
- `docs/pr-ready-audit-final-batch-2026-04-08.md`

Langkah saat dilanjutkan:

1. gunakan commit `2b20228` sebagai baseline PR audit final
2. jangan campurkan batch ini lagi dengan residual API besar lain

## Hotfix Kecil Terkait Validasi

Status:

- parse hotfix kecil sudah di-commit terpisah
- commit: `3fe4b86` `fix(app): remove stray characters from tenant default role setup service`

Dokumen referensi:

- `docs/hotfix-tenant-default-role-parse-2026-04-08.md`

Catatan:

- `app/Services/TenantDefaultRoleSetupService.php` sempat punya karakter liar sebelum tag `<?php`
- hotfix ini tidak semestinya dicampur ke PR web-route jika ingin slice tetap bersih

## 2. PR Web Routes / Portal Rewrite

Status:

- sudah di-commit terpisah
- commit: `dd055f6` `refactor(web): move portal and detail page route flow into controllers`
- analisis residual dan temuan awal sudah ada
- hardening kecil untuk authenticated portal login redirect sudah dikerjakan di `PortalAuthController` + `PortalWebAccessTest`
- hardening kecil untuk generic auth guest redirect sudah dikerjakan di `bootstrap/app.php` + `PortalWebAccessTest`
- coverage legacy redirect map sudah diperluas untuk fallback wrapper tanpa entity id dan preservasi query string `sales-history`
- helper redirect sales legacy sekarang dipusatkan di `WebSalesFlowContext` dengan coverage tambahan untuk `sales-void-refund` dan `receipt-preview`
- provisioning onboarding step 2 dan step 4 sudah diekstrak ke service terpisah, lalu root/register dan orchestration onboarding step 1-4 juga sudah dipindah ke `OnboardingController`; regression onboarding feature + smoke tetap hijau
- wrapper legacy redirect berulang sekarang mulai dipusatkan di `LegacyWebRedirectService`; regression `PortalWebAccessTest --filter=legacy_` tetap hijau
- logout dan portal switch sudah dipindahkan keluar dari route closure ke `PortalSessionController` + `PortalSwitchService`; regression `portal_switch`, `logout`, dan `legacy_` tetap hijau
- menu dan menu section sudah dipindah ke `MenuController`; smoke menu/access regression tetap hijau
- helper closure tenant/view di `routes/web.php` sekarang dipusatkan ke `WebRouteContextService`, sehingga file route tidak lagi menyimpan resolver kecil berulang untuk `tenantId`, `withTenant`, dan status shift
- grouped simple view wiring POS/backoffice sekarang mayoritas dipindah ke `TenantViewController` dengan route defaults (`view`, `data`, `routeParam`, `viewDataKey`)
- wrapper redirect ringan sekarang dipusatkan di `LegacyWebRedirectController`; placeholder target seperti `{account}` ikut diinterpolasi dari route param sehingga legacy redirect map tetap aman
- regression batch terbaru hijau: `PortalWebAccessTest --filter=legacy_` PASS lagi setelah fix interpolasi target, dan `WebOperationalPagesSmokeTest` tetap PASS
- closure dinamis berbasis `WebSalesFlowContext` dan `WebEntityFlowContext` sekarang juga sudah keluar dari route file ke `PosPageController` dan `BackofficePageController`
- gate + expiry handling untuk `/platform/dashboard` sekarang dipindah ke `PlatformDashboardController`, termasuk audit log `tenant_impersonation_ended`
- `routes/web.php` sekarang praktis tinggal route wiring, group wrapper, dan redirect statis kecil (`/pos` dan `/backoffice`)
- regression batch terbaru hijau: `PortalWebAccessTest` PASS penuh (109 test), `WebOperationalPagesSmokeTest` PASS, dan `PlatformSuperadminDangerActionsTest --filter=impersonation_expiry_logs_audit_end_event` PASS

Scope utama:

- root/login flow
- portal switch
- onboarding provisioning
- legacy redirects
- pos/backoffice grouped routes

Dokumen referensi:

- `docs/review-web-routes-residual-2026-04-08.md`
- `docs/review-web-routes-findings-2026-04-08.md`
- `docs/next-batch-web-routes-plan-2026-04-08.md`
- `docs/pr-summary-web-routes-portal-rewrite-2026-04-08.md`
- `docs/pr-ready-web-routes-portal-rewrite-2026-04-08.md`
- `docs/commit-message-web-routes-portal-rewrite-2026-04-08.md`

Prioritas risiko:

- redirect loop atau landing portal salah
- parity view context antara controller baru dan route closure lama harus dijaga saat ada perubahan UI berikutnya

File set PR yang sekarang sudah cukup kohesif untuk batch ini:

- `app/Http/Controllers/Web/PortalAuthController.php`
- `app/Http/Controllers/Web/PortalSessionController.php`
- `app/Http/Controllers/Web/OnboardingController.php`
- `app/Http/Controllers/Web/MenuController.php`
- `app/Http/Controllers/Web/TenantViewController.php`
- `app/Http/Controllers/Web/PosPageController.php`
- `app/Http/Controllers/Web/BackofficePageController.php`
- `app/Http/Controllers/Web/PlatformDashboardController.php`
- `app/Http/Controllers/Web/LegacyWebRedirectController.php`
- `app/Services/PortalSwitchService.php`
- `app/Services/WebRouteContextService.php`
- `routes/web.php`

Smoke checklist minimal untuk PR ini:

1. `php artisan test tests/Feature/PortalWebAccessTest.php --filter=legacy_`
2. `php artisan test tests/Feature/WebOperationalPagesSmokeTest.php`
3. `php artisan test tests/Feature/WebAuthOnboardingSmokeTest.php`
4. `php artisan test tests/Feature/OnboardingFirstTimeFlowTest.php`
5. `php artisan test tests/Feature/PlatformSuperadminDangerActionsTest.php --filter=impersonation_expiry_logs_audit_end_event`

Langkah saat dilanjutkan:

1. gunakan commit `dd055f6` sebagai baseline PR web-route terpisah
2. pertahankan PR ini fokus ke web-route slimming dan portal flow saja; abaikan diff aktif lain yang tidak terkait
3. jika ada follow-up web, posisikan sebagai batch baru di atas commit `dd055f6`, bukan dicampur ke slice ini

## 3. PR API Expansion

Status:

- belum dikerjakan sebagai PR terpisah
- analisis residual dan findings awal sudah ada
- batch kecil terdekat yang siap diambil lebih dulu adalah `PR Audit Final` pada tiga controller API observability
- setelah audit final dipisah, kandidat sub-batch paling kecil berikutnya adalah refund surface

Scope utama:

- `routes/api.php`
- inventory report endpoints
- refund endpoints
- subscription endpoints
- valuation gating changes

Dokumen referensi:

- `docs/review-api-residual-2026-04-08.md`
- `docs/review-api-findings-2026-04-08.md`
- `docs/pr-summary-refund-legacy-audit-2026-04-09.md`
- `docs/pr-ready-refund-legacy-audit-2026-04-09.md`
- `docs/commit-message-refund-legacy-audit-2026-04-09.md`
- `docs/pr-summary-inventory-reporting-2026-04-09.md`
- `docs/pr-ready-inventory-reporting-2026-04-09.md`
- `docs/commit-message-inventory-reporting-2026-04-09.md`
- `docs/pr-summary-platform-admin-api-2026-04-09.md`
- `docs/pr-ready-platform-admin-api-2026-04-09.md`
- `docs/commit-message-platform-admin-api-2026-04-09.md`

Prioritas risiko:

- `auth:sanctum` sekarang global untuk `/api/v1`
- valuation access sekarang tergantung role + feature gate + subscription status
- refund dan subscription sudah menjadi surface fitur baru, bukan perubahan kecil
- refund core baseline yang benar untuk lanjutan adalah commit `48558ab` lalu `d634cd1`
- refund legacy audit remediation perlu tetap variant-aware agar historical row tidak salah terikat saat satu produk punya beberapa variant dalam sale yang sama

Baseline validasi terbaru untuk refund legacy audit:

- `php artisan test tests/Feature/RefundLegacyAuditApiTest.php tests/Feature/RefundLegacyAuditCommandTest.php tests/Feature/RefundLegacyAuditWebSmokeTest.php` PASS (`16` test, `130` assertion)

Status eksekusi terbaru:

- refund legacy audit sudah di-commit: `7bc4553` `feat(api): add refund legacy audit and remediation surface`
- subscription core + invoice sudah di-commit: `b0d8590` `feat(api): add subscription core and invoice endpoints`
- inventory reporting sudah di-commit: `b7e6cec` `feat(api): add inventory reporting endpoints`
- platform admin API sudah siap dipisahkan sebagai slice tersendiri dengan focused validation hijau

Langkah saat dilanjutkan:

1. gunakan commit `7bc4553`, `b0d8590`, dan `b7e6cec` sebagai baseline API slices yang sudah terpisah
2. pisahkan platform admin API sebagai subsystem tersendiri:
	- `app/Http/Controllers/Api/Platform/*`
	- `app/Http/Requests/Platform/*`
	- `app/Http/Resources/Platform/*`
	- `app/Services/Platform/*`
	- `app/tests/Feature/PlatformSuperadminReadApiTest.php`
	- `app/tests/Feature/PlatformSuperadminDangerActionsTest.php`
	- `app/routes/api_platform.php`
	- hunk route registration terkait di `app/bootstrap/app.php`
3. lanjut middleware dependency pass setelah platform dipisah
4. sisakan commands/deploy sebagai batch operasional terakhir
5. hindari mencampur guest redirect bootstrap dan portal host config ke commit platform

## 4. PR Middleware Access / Tenant / Subscription Gate

Status:

- belum dikerjakan sebagai PR terpisah
- dependency map sudah ada

Scope utama:

- `RoleMiddleware`
- `TenantResolver`
- `FeatureGateMiddleware`
- `SubscriptionStatusMiddleware`
- `WebAccessMiddleware`
- `CanonicalPortalHostMiddleware`
- `SecurityHeadersMiddleware`

Dokumen referensi:

- `docs/review-middleware-residual-2026-04-08.md`
- `docs/middleware-dependency-summary-2026-04-08.md`

Prioritas risiko:

- tenant mismatch menghasilkan 400/403 baru
- normalized role resolution mengubah access behavior
- CSP/security headers berlaku global
- route web dan API baru sangat tergantung pada middleware ini

Langkah saat dilanjutkan:

1. review middleware bersama route batch yang memakainya
2. pastikan alias middleware dan bootstrap wiring konsisten
3. siapkan smoke tests untuk access denial, tenant mismatch, dan CSP impact

Status eksekusi terbaru:

- platform admin API sudah di-commit: `491f61b` `feat(api): add platform admin endpoints`
- middleware dependency slice sudah tervalidasi fokus pada CSP, portal access, onboarding host config, subscription gating, valuation authorization, dan tenant scoping
- commands/deploy workflow sekarang menjadi batch operasional tersisa setelah middleware dipisah

## 5. PR Subscription / Refund / Platform Surface

Status:

- subscription core sudah di-commit terpisah
- refund legacy audit sudah di-commit terpisah
- inventory reporting sudah di-commit terpisah
- platform admin API sudah terimplementasi di worktree dan lolos focused tests, tinggal dipisah sebagai commit tersendiri

Scope utama:

- `SubscriptionController`
- `SubscriptionInvoiceController`
- `RefundController`
- `RefundLegacyAuditController`
- `Http/Controllers/Api/Platform/*`
- `Http/Requests/Platform/*`
- `Services/Platform/*`

Dokumen referensi:

- `docs/next-batch-subscription-platform-plan-2026-04-08.md`
- `docs/pr-summary-subscription-core-invoices-2026-04-09.md`
- `docs/pr-ready-subscription-core-invoices-2026-04-09.md`
- `docs/commit-message-subscription-core-invoices-2026-04-09.md`
- `docs/pr-summary-platform-admin-api-2026-04-09.md`
- `docs/pr-ready-platform-admin-api-2026-04-09.md`
- `docs/commit-message-platform-admin-api-2026-04-09.md`

Langkah saat dilanjutkan:

1. gunakan commit `7bc4553`, `b0d8590`, dan `b7e6cec` sebagai baseline slice API yang sudah dipisah
2. commit platform admin API sebagai subsystem terpisah
3. lanjut middleware dependency pass
4. sisakan commands/deploy untuk batch operasional setelah middleware

## 6. PR Commands / Deploy Workflow

Status:

- belum dikerjakan sebagai PR terpisah

Scope utama:

- `routes/console.php`
- `app/Console/Commands/*`
- `.github/workflows/deploy-staging.yml`

Prioritas risiko:

- workflow deploy menyentuh SSH, migrate, cache, healthcheck, rollback, dan queue worker detection
- command audit/backfill/remediation tidak layak ikut PR sempit tanpa review operasional

Dokumen referensi tambahan:

- `docs/pr-summary-middleware-access-gates-2026-04-09.md`
- `docs/pr-ready-middleware-access-gates-2026-04-09.md`
- `docs/commit-message-middleware-access-gates-2026-04-09.md`

Status eksekusi terbaru:

- middleware access/gating sudah di-commit: `05fdf5f` `feat(app): add middleware access and portal gating layer`
- batch operasional tersisa sekarang hanya command/deploy workflow

Dokumen referensi tambahan untuk batch operasional:

- `docs/pr-summary-ops-commands-deploy-2026-04-09.md`
- `docs/pr-ready-ops-commands-deploy-2026-04-09.md`
- `docs/commit-message-ops-commands-deploy-2026-04-09.md`

Langkah saat dilanjutkan:

1. review command safety dan dependency produksi
2. review secret/env expectation di workflow deploy
3. pisahkan sebagai PR infra/ops bila perlu

## Catatan Umum

- Jangan campur PR Audit Final dengan batch residual besar lain.
- Saat lanjut sesi berikutnya, mulai dari dokumen ini lalu pilih salah satu PR pending untuk dibedah lebih lanjut.
- Handoff final keseluruhan ada di `docs/final-handoff-audit-and-residual-2026-04-08.md`.