# Residual Worktree Risk 2026-04-08

Dokumen ini merangkum perubahan besar yang masih bercampur di worktree dan tidak sebaiknya ikut otomatis ke PR audit final.

## Ringkasan Risiko

Area berikut merupakan sumber risiko tertinggi karena mengubah surface aplikasi secara luas:

1. Routing dan portal web.
2. API surface baru untuk refund, subscription, inventory report, dan platform admin.
3. Middleware akses, tenant, feature gate, dan subscription status.
4. Command operasional dan workflow deploy staging.

## Area Risiko Tinggi

### 1. Rewrite Portal dan Web Routing

File utama:

- `routes/web.php`

Temuan:

- Diff sangat besar pada `routes/web.php`.
- Root route berubah ke login flow.
- Onboarding flow ikut dirombak, termasuk setup tenant, role owner, branch/location default, accounting setup, dan auto-login ke portal POS.
- Banyak legacy route sekarang hanya redirect ke portal baru (`/pos/*`, `/backoffice/*`).
- Ada pemisahan akses POS vs backoffice, portal switching, host canonicalization, dan flow context baru.

Risiko:

- Regression navigasi web/manual QA paling tinggi ada di sini.
- Redirect loop, portal mismatch, atau akses halaman lama bisa pecah walau API tetap hijau.
- Onboarding dan login menjadi area sensitif karena sekarang tersambung ke role setup dan tenant setup.

### 2. Ekspansi API Surface

File utama:

- `routes/api.php`

Temuan:

- Semua API sekarang dipaksa lewat `auth:sanctum`.
- Banyak route baru ditambahkan: inventory reports, refunds, refund legacy audit, subscription, subscription invoices.
- Banyak role guard diperluas dari `admin` ke kombinasi `owner`, `warehouse`, `supervisor`, `superadmin`.
- Beberapa endpoint valuation sekarang digate oleh `feature_gate` dan `subscription_status`.

Risiko:

- Behavior akses API berubah global, bukan lokal ke batch audit final.
- Client lama yang sebelumnya mengandalkan akses lokal tanpa auth akan pecah.
- Gating subscription bisa memunculkan 403 baru pada jalur yang sebelumnya hanya diuji secara fungsional.

### 3. Middleware Akses dan Tenant

File utama:

- `app/Http/Middleware/RoleMiddleware.php`
- `app/Http/Middleware/TenantResolver.php`
- `app/Http/Middleware/CanonicalPortalHostMiddleware.php`
- `app/Http/Middleware/FeatureGateMiddleware.php`
- `app/Http/Middleware/PortalSeparationMiddleware.php`
- `app/Http/Middleware/SecurityHeadersMiddleware.php`
- `app/Http/Middleware/SubscriptionStatusMiddleware.php`
- `app/Http/Middleware/WebAccessMiddleware.php`

Temuan:

- `RoleMiddleware` sekarang sepenuhnya bergantung pada normalized role resolution.
- `TenantResolver` sekarang lebih ketat terhadap mismatch tenant antara request context dan user tenant.
- Ada middleware baru untuk canonical portal host, feature gates, web access, subscription status, dan security headers.

Risiko:

- Ini bukan perubahan kosmetik; ini mengubah keputusan akses inti.
- Salah wiring di bootstrap middleware atau route middleware alias bisa menyebabkan 401/403 luas.
- Security headers dan CSP baru dapat memukul rendering web bila ada asset/script yang belum di-whitelist.

### 4. Subscription dan Platform Surface

File utama:

- `app/Http/Controllers/Api/SubscriptionController.php`
- `app/Http/Controllers/Api/SubscriptionInvoiceController.php`
- `app/Http/Controllers/Api/RefundController.php`
- `app/Http/Controllers/Api/RefundLegacyAuditController.php`
- `app/Http/Controllers/Api/Platform/*`
- `app/Http/Requests/Platform/*`
- `app/Services/Platform/*`

Temuan:

- Ada surface subscription lengkap: current plan, entitlements, impact preview, audit logs, invoices, mark paid.
- Ada surface platform admin lengkap: tenant, user, invoice, anomaly, audit log, support, settings, dashboard, payment, plan change.
- Ada refund flow API baru dan audit remediation flow untuk legacy refund.

Risiko:

- Ini sudah seperti batch fitur terpisah, bukan bagian kecil dari audit final.
- Butuh test dan review domain sendiri sebelum ikut ke PR yang sempit.
- Perubahan ini menyentuh billing, access control, support tooling, dan data remediation.

### 5. Command dan Workflow Operasional

File utama:

- `app/Console/Commands/EnforceSubscriptionLifecycleCommand.php`
- `app/Console/Commands/PurgeAuditLogsCommand.php`
- `.github/workflows/deploy-staging.yml`
- `routes/console.php`

Temuan:

- `routes/console.php` menambah banyak command audit/backfill/remediation.
- Ada command baru untuk enforce lifecycle subscription dan purge audit logs.
- Workflow deploy staging baru menambahkan deploy SSH, migrate, cache optimize, healthcheck, queue worker check, dan rollback trap.

Risiko:

- Salah konfigurasi secret atau environment bisa memblok deploy.
- Command backfill/remediation butuh review operasional sebelum dijalankan di lingkungan nyata.
- Workflow deploy tidak cocok digabung ke PR audit sempit tanpa validasi infra.

## Rekomendasi Pemisahan

Jangan gabungkan area berikut ke PR audit final:

- `routes/web.php`
- `routes/api.php`
- `routes/console.php`
- seluruh middleware baru terkait portal/subscription/feature gate
- seluruh surface subscription/platform/refund baru
- workflow deploy staging baru

## Urutan Review Setelah Batch Audit Final Dipisah

1. Review portal/web routing.
2. Review middleware access and tenant resolution.
3. Review subscription and platform APIs.
4. Review operational commands and deploy workflow.

## Status Batch Audit Final

Batch audit final yang sudah dipisahkan tetap mengacu ke:

- `app/Http/Controllers/Api/BootstrapController.php`
- `app/Http/Controllers/Api/CheckoutController.php`
- `app/Http/Controllers/Api/FinanceControlDashboardController.php`

File test terkait yang sudah tervalidasi tetapi tidak lagi muncul sebagai diff aktif:

- `tests/Feature/FinanceControlDashboardTest.php`

Lihat juga dokumen pasangan:

- `docs/audit-final-batch-2026-04-08.md`