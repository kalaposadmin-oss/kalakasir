# PR Ready Platform Admin API 2026-04-09

## PR Title

Add platform admin API endpoints

## Commit Message

```text
feat(api): add platform admin endpoints

Add the platform admin API surface for superadmin dashboards, cross-tenant support,
and controlled danger actions.

- add platform dashboard, tenant, user, invoice, payment, anomaly, support, and settings endpoints
- add tenant and user danger actions with idempotency, audit logging, and throttling
- isolate platform routes into routes/api_platform.php with dedicated bootstrap registration
- include focused platform read and danger-action coverage

Validated with focused platform superadmin feature tests.
```

## PR Description

```markdown
## Scope

This PR isolates the platform admin API surface for superadmin-only read and action endpoints. It intentionally keeps middleware dependency cleanup, portal host config, bootstrap guest redirect changes, and operational command/deploy work out of scope.

## Included Files

- app/Http/Controllers/Api/Platform/*
- app/Http/Requests/Platform/*
- app/Http/Resources/Platform/*
- app/Services/Platform/*
- tests/Feature/PlatformSuperadminReadApiTest.php
- tests/Feature/PlatformSuperadminDangerActionsTest.php
- routes/api_platform.php
- bootstrap/app.php route-registration hunk for api_platform.php

## What Changed

- add platform dashboard, tenant, user, invoice, payment, anomaly, support, and settings endpoints for superadmin workflows
- add tenant and user danger actions with idempotency, rate limiting, and audit logging
- add cross-tenant anomaly resolution and platform audit export endpoints
- isolate platform route wiring in routes/api_platform.php and register it under /api/platform and /api/v1/platform

## Validation

- php artisan test tests/Feature/PlatformSuperadminReadApiTest.php tests/Feature/PlatformSuperadminDangerActionsTest.php passed
- combined focused run passed with 42 tests and 178 assertions

## Explicitly Excluded

- guest redirect and middleware alias changes in bootstrap/app.php
- config/platform.php portal host settings
- middleware dependency pass
- commands and deploy workflow
```

## Exact Stage List

Stage hanya file berikut jika ingin slice platform admin tetap bersih:

- `app/app/Http/Controllers/Api/Platform/*`
- `app/app/Http/Requests/Platform/*`
- `app/app/Http/Resources/Platform/*`
- `app/app/Services/Platform/*`
- `app/tests/Feature/PlatformSuperadminReadApiTest.php`
- `app/tests/Feature/PlatformSuperadminDangerActionsTest.php`
- `app/routes/api_platform.php`
- hunk `withRouting(... then: ...)` untuk `api_platform.php` di `app/bootstrap/app.php`
- `app/docs/pr-summary-platform-admin-api-2026-04-09.md`
- `app/docs/pr-ready-platform-admin-api-2026-04-09.md`
- `app/docs/commit-message-platform-admin-api-2026-04-09.md`

## Keep Out Of This PR

Jangan ikutkan:

- perubahan `redirectGuestsTo` di `app/bootstrap/app.php`
- alias `feature_gate`, `canonical_portal_host`, `portal`, `subscription_status`, `web_access` di `app/bootstrap/app.php`
- append `SecurityHeadersMiddleware` di `app/bootstrap/app.php`
- `app/config/platform.php`
- middleware files
- `routes/console.php`
- workflow deploy staging

## Exact Git Commands

Gunakan stage biasa untuk file penuh berikut:

```powershell
git add app/app/Http/Controllers/Api/Platform app/app/Http/Requests/Platform app/app/Http/Resources/Platform app/app/Services/Platform app/tests/Feature/PlatformSuperadminReadApiTest.php app/tests/Feature/PlatformSuperadminDangerActionsTest.php app/routes/api_platform.php app/docs/pr-summary-platform-admin-api-2026-04-09.md app/docs/pr-ready-platform-admin-api-2026-04-09.md app/docs/commit-message-platform-admin-api-2026-04-09.md
```

Untuk `app/bootstrap/app.php`, stage hanya hunk `then:` yang mendaftarkan `routes/api_platform.php`.

Setelah itu commit dengan:

```powershell
git commit -m "feat(api): add platform admin endpoints"
```