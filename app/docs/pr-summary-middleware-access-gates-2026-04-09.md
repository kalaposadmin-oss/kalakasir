# PR Summary Middleware Access Gates 2026-04-09

## Scope

PR ini hanya membawa middleware access/gating layer yang menjadi dependency runtime untuk route web dan API baru. Commands/deploy workflow dan feature-specific controller changes tetap di luar scope.

## Included Files

- `app/Http/Middleware/RoleMiddleware.php`
- `app/Http/Middleware/TenantResolver.php`
- `app/Http/Middleware/FeatureGateMiddleware.php`
- `app/Http/Middleware/SubscriptionStatusMiddleware.php`
- `app/Http/Middleware/CanonicalPortalHostMiddleware.php`
- `app/Http/Middleware/PortalSeparationMiddleware.php`
- `app/Http/Middleware/WebAccessMiddleware.php`
- `app/Http/Middleware/SecurityHeadersMiddleware.php`
- `bootstrap/app.php`
- `config/platform.php`
- focused validation tests untuk CSP, portal access, onboarding host config, tenant resolution, dan API gating

## What Changed

### API Access and Tenant Gating

- `RoleMiddleware` sekarang memakai `UserRoleResolverService` untuk role normalization yang konsisten dan menghapus bypass lokal yang terlalu longgar
- `TenantResolver` mewajibkan tenant context valid untuk request API dan mengembalikan `TENANT_NOT_RESOLVED` atau `TENANT_MISMATCH` secara eksplisit
- `FeatureGateMiddleware` memblokir route berdasarkan entitlement paket aktif tenant
- `SubscriptionStatusMiddleware` memblokir route saat status langganan tidak mengizinkan operasi

### Web Portal and Security Layer

- `CanonicalPortalHostMiddleware` mengarahkan request login/portal ke host kanonik POS atau backoffice
- `PortalSeparationMiddleware` menjaga request tetap berada di portal yang benar dan menghindari cross-portal drift
- `WebAccessMiddleware` menyatukan gate autentikasi web, portal aktif, role allowed, impersonation superadmin, dan feature access
- `SecurityHeadersMiddleware` menambahkan CSP nonce-based untuk HTML dan header keamanan global

### Bootstrap and Config Wiring

- `bootstrap/app.php` menambahkan `SecurityHeadersMiddleware` dan alias `feature_gate`, `canonical_portal_host`, `portal`, `subscription_status`, serta `web_access`
- guest redirect bootstrap sekarang mengarah ke onboarding jika tenant belum punya user, atau ke `/login` untuk flow normal
- `config/platform.php` menambahkan konfigurasi `platform.portals.pos_hosts` dan `platform.portals.backoffice_hosts`

## Validation

- `php artisan test tests/Feature/SecurityHeadersTest.php tests/Feature/PortalWebAccessTest.php tests/Feature/WebAuthOnboardingSmokeTest.php tests/Feature/SubscriptionModuleHardeningTest.php tests/Feature/ValuationAuthorizationTest.php tests/Feature/SupplierTenantScopingTest.php` passed
- combined focused run: `145` tests passed, `724` assertions

## Explicitly Excluded

PR ini sengaja tidak membawa:

- `routes/console.php`
- `app/Console/Commands/*`
- `.github/workflows/deploy-staging.yml`
- controller/route feature batch yang sudah di-commit sebelumnya
- `RequestContextMiddleware.php` karena tidak ada perubahan logic yang relevan untuk access/gating slice ini