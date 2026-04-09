# PR Ready Middleware Access Gates 2026-04-09

## PR Title

Add middleware access and portal gating layer

## Commit Message

```text
feat(app): add middleware access and portal gating layer

Add the middleware runtime layer used by the new API and web route surfaces.

- normalize role checks and tenant resolution for API requests
- add feature gate and subscription status middleware contracts
- add portal host, portal separation, and web access middleware for web flows
- apply nonce-based security headers and portal host configuration wiring

Validated with focused middleware, portal access, and gating feature tests.
```

## PR Description

```markdown
## Scope

This PR isolates the middleware runtime layer that the newer API and web route surfaces depend on. It intentionally keeps command/deploy work and unrelated controller batches out of scope.

## Included Files

- app/Http/Middleware/RoleMiddleware.php
- app/Http/Middleware/TenantResolver.php
- app/Http/Middleware/FeatureGateMiddleware.php
- app/Http/Middleware/SubscriptionStatusMiddleware.php
- app/Http/Middleware/CanonicalPortalHostMiddleware.php
- app/Http/Middleware/PortalSeparationMiddleware.php
- app/Http/Middleware/WebAccessMiddleware.php
- app/Http/Middleware/SecurityHeadersMiddleware.php
- bootstrap/app.php
- config/platform.php
- focused tests for portal access, security headers, host config, tenant scoping, and subscription gating

## What Changed

- enforce normalized role checks and strict tenant resolution for API requests
- add explicit feature and subscription status gate middleware responses
- add canonical portal host redirects and portal separation for web flows
- centralize web access checks for portal, role, impersonation, and feature entitlements
- apply nonce-based CSP security headers globally and wire portal host configuration

## Validation

- php artisan test tests/Feature/SecurityHeadersTest.php tests/Feature/PortalWebAccessTest.php tests/Feature/WebAuthOnboardingSmokeTest.php tests/Feature/SubscriptionModuleHardeningTest.php tests/Feature/ValuationAuthorizationTest.php tests/Feature/SupplierTenantScopingTest.php passed
- combined focused run passed with 145 tests and 724 assertions

## Explicitly Excluded

- commands and deploy workflow
- unrelated controller and route feature slices already committed separately
- RequestContextMiddleware
```

## Exact Stage List

Stage hanya file berikut jika ingin slice middleware tetap bersih:

- `app/app/Http/Middleware/RoleMiddleware.php`
- `app/app/Http/Middleware/TenantResolver.php`
- `app/app/Http/Middleware/FeatureGateMiddleware.php`
- `app/app/Http/Middleware/SubscriptionStatusMiddleware.php`
- `app/app/Http/Middleware/CanonicalPortalHostMiddleware.php`
- `app/app/Http/Middleware/PortalSeparationMiddleware.php`
- `app/app/Http/Middleware/WebAccessMiddleware.php`
- `app/app/Http/Middleware/SecurityHeadersMiddleware.php`
- `app/bootstrap/app.php`
- `app/config/platform.php`
- `app/tests/Feature/SecurityHeadersTest.php`
- `app/tests/Feature/PortalWebAccessTest.php`
- `app/tests/Feature/WebAuthOnboardingSmokeTest.php`
- `app/tests/Feature/SubscriptionModuleHardeningTest.php`
- `app/tests/Feature/ValuationAuthorizationTest.php`
- `app/tests/Feature/SupplierTenantScopingTest.php`
- `app/docs/pr-summary-middleware-access-gates-2026-04-09.md`
- `app/docs/pr-ready-middleware-access-gates-2026-04-09.md`
- `app/docs/commit-message-middleware-access-gates-2026-04-09.md`

## Keep Out Of This PR

Jangan ikutkan:

- `app/app/Http/Middleware/RequestContextMiddleware.php`
- `routes/console.php`
- `app/Console/Commands/*`
- `.github/workflows/deploy-staging.yml`
- test feature lain yang hanya kebetulan ikut hijau karena middleware ini aktif

## Exact Git Commands

```powershell
git add app/app/Http/Middleware/RoleMiddleware.php app/app/Http/Middleware/TenantResolver.php app/app/Http/Middleware/FeatureGateMiddleware.php app/app/Http/Middleware/SubscriptionStatusMiddleware.php app/app/Http/Middleware/CanonicalPortalHostMiddleware.php app/app/Http/Middleware/PortalSeparationMiddleware.php app/app/Http/Middleware/WebAccessMiddleware.php app/app/Http/Middleware/SecurityHeadersMiddleware.php app/bootstrap/app.php app/config/platform.php app/tests/Feature/SecurityHeadersTest.php app/tests/Feature/PortalWebAccessTest.php app/tests/Feature/WebAuthOnboardingSmokeTest.php app/tests/Feature/SubscriptionModuleHardeningTest.php app/tests/Feature/ValuationAuthorizationTest.php app/tests/Feature/SupplierTenantScopingTest.php app/docs/pr-summary-middleware-access-gates-2026-04-09.md app/docs/pr-ready-middleware-access-gates-2026-04-09.md app/docs/commit-message-middleware-access-gates-2026-04-09.md
```

Setelah itu commit dengan:

```powershell
git commit -m "feat(app): add middleware access and portal gating layer"
```