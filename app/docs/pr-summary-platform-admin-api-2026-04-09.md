# PR Summary Platform Admin API 2026-04-09

## Scope

PR ini hanya membawa platform admin API surface. Middleware dependency pass, portal host config, guest redirect bootstrap changes, dan commands/deploy workflow tetap di luar scope.

## Included Files

- `app/Http/Controllers/Api/Platform/*`
- `app/Http/Requests/Platform/*`
- `app/Http/Resources/Platform/*`
- `app/Services/Platform/*`
- `tests/Feature/PlatformSuperadminReadApiTest.php`
- `tests/Feature/PlatformSuperadminDangerActionsTest.php`
- `routes/api_platform.php`
- hunk route registration `api_platform.php` di `bootstrap/app.php`

## What Changed

### Platform Read Surface

- menambahkan dashboard platform untuk total tenant, health summary, overdue invoice, dan recent events
- menambahkan list/detail tenant platform dengan usage summary, health status, dan preview user/event
- menambahkan list/detail user platform beserta audit log pengguna
- menambahkan list/detail invoice platform, list pembayaran, list/export plan changes, list/export audit logs, list/resolve anomaly, support search/actions, dan platform health/settings read endpoints

### Platform Danger Actions

- menambahkan action suspend, unsuspend, impersonate, force logout, reset owner password, dan unlock tenant
- menambahkan action reset password, force logout, dan unlock user
- menambahkan idempotent anomaly resolution lintas tenant untuk superadmin
- semua danger action memakai throttle `platform-danger` dan audit log operasional

### Route Isolation

- memindahkan seluruh route platform ke `routes/api_platform.php`
- mendaftarkan file route platform via hunk `then:` di `bootstrap/app.php` tanpa mencampur perubahan bootstrap lain

## Validation

- `php artisan test tests/Feature/PlatformSuperadminReadApiTest.php tests/Feature/PlatformSuperadminDangerActionsTest.php` passed
- combined focused run: `42` tests passed, `178` assertions

## Explicitly Excluded

PR ini sengaja tidak membawa:

- guest redirect change di `bootstrap/app.php`
- alias middleware tambahan di `bootstrap/app.php`
- `config/platform.php` host portal config
- `FeatureGateMiddleware.php`
- `SubscriptionStatusMiddleware.php`
- `CanonicalPortalHostMiddleware.php`
- `SecurityHeadersMiddleware.php`
- `WebAccessMiddleware.php`
- `routes/console.php`
- `.github/workflows/deploy-staging.yml`