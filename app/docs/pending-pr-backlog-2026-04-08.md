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

Prioritas risiko:

- `auth:sanctum` sekarang global untuk `/api/v1`
- valuation access sekarang tergantung role + feature gate + subscription status
- refund dan subscription sudah menjadi surface fitur baru, bukan perubahan kecil
- refund core baseline yang benar untuk lanjutan adalah commit `48558ab` lalu `d634cd1`
- refund legacy audit remediation perlu tetap variant-aware agar historical row tidak salah terikat saat satu produk punya beberapa variant dalam sale yang sama

Baseline validasi terbaru untuk refund legacy audit:

- `php artisan test tests/Feature/RefundLegacyAuditApiTest.php tests/Feature/RefundLegacyAuditCommandTest.php tests/Feature/RefundLegacyAuditWebSmokeTest.php` PASS (`16` test, `130` assertion)

Langkah saat dilanjutkan:

1. pisahkan refund legacy audit sebagai sub-batch API expansion terkecil di atas baseline refund core:
	- `app/Http/Controllers/Api/RefundController.php`
	- `app/Http/Controllers/Api/RefundLegacyAuditController.php`
	- `app/Services/RefundLegacyAuditService.php`
	- `app/tests/Feature/RefundLegacyAuditApiTest.php`
	- `app/tests/Feature/RefundLegacyAuditCommandTest.php`
	- `app/tests/Feature/RefundLegacyAuditWebSmokeTest.php`
	- potongan terkait di `routes/api.php`
2. lanjut subscription setelah refund legacy audit
3. tinggalkan inventory reporting sebagai batch terpisah karena controller tunggalnya sangat besar
4. cek contract break yang mungkin ke client lama
5. siapkan smoke/API checklist minimal per surface

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

## 5. PR Subscription / Refund / Platform Surface

Status:

- belum dikerjakan sebagai PR terpisah
- plan awal batch berikutnya sudah ada
- refund core sudah punya baseline commit (`48558ab`, `d634cd1`)
- refund legacy audit sekarang siap dipisah sebagai slice lanjutan kecil dengan command/API/web smoke coverage

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

Langkah saat dilanjutkan:

1. selesaikan pemisahan refund legacy audit
2. mulai dari subscription core
3. lanjut inventory reporting dependency bila masih terkait
4. review platform admin API sebagai subsystem terpisah

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

Langkah saat dilanjutkan:

1. review command safety dan dependency produksi
2. review secret/env expectation di workflow deploy
3. pisahkan sebagai PR infra/ops bila perlu

## Catatan Umum

- Jangan campur PR Audit Final dengan batch residual besar lain.
- Saat lanjut sesi berikutnya, mulai dari dokumen ini lalu pilih salah satu PR pending untuk dibedah lebih lanjut.
- Handoff final keseluruhan ada di `docs/final-handoff-audit-and-residual-2026-04-08.md`.