# PR Ready Web Routes Portal Rewrite 2026-04-08

## PR Title

Slim web routes by moving portal and detail page flow into dedicated web controllers

## Commit Message

```text
refactor(web): move portal and detail page route flow into controllers

Slim down routes/web.php by moving remaining portal and detail-page
closures into dedicated web controllers.

- extract POS page flow into PosPageController
- extract backoffice detail page flow into BackofficePageController
- move platform dashboard gate and impersonation expiry handling into PlatformDashboardController
- keep legacy redirect and portal behavior unchanged while making routes declarative

Validated with focused portal, operational page, and platform dashboard web tests.
```

## PR Description

```markdown
## Scope

This PR isolates the web-route slimming batch and keeps it separate from the much larger API, middleware, subscription, platform, and deployment changes still active in the worktree.

## Included Files

- app/Http/Controllers/Web/PortalAuthController.php
- app/Http/Controllers/Web/PortalSessionController.php
- app/Http/Controllers/Web/OnboardingController.php
- app/Http/Controllers/Web/MenuController.php
- app/Http/Controllers/Web/TenantViewController.php
- app/Http/Controllers/Web/LegacyWebRedirectController.php
- app/Http/Controllers/Web/PosPageController.php
- app/Http/Controllers/Web/BackofficePageController.php
- app/Http/Controllers/Web/PlatformDashboardController.php
- app/Services/PortalSwitchService.php
- app/Services/WebRouteContextService.php
- routes/web.php

## What Changed

### Route Slimming

- Moved tenant/view helper logic into WebRouteContextService.
- Moved simple tenant-aware grouped views into TenantViewController.
- Moved remaining POS and backoffice detail-page route closures into dedicated controllers.
- Replaced the final tiny route action closures with declarative redirects.

### Portal Flow

- Preserved role-aware portal landing and portal switching behavior.
- Preserved legacy redirect compatibility including placeholder interpolation.
- Kept back-link and owner-shortcut behavior intact for POS and backoffice pages.

### Platform Dashboard Web

- Moved superadmin gate and impersonation expiry audit behavior for /platform/dashboard into PlatformDashboardController.

## Validation

- php artisan test tests/Feature/PortalWebAccessTest.php passed with 109 tests.
- php artisan test tests/Feature/WebOperationalPagesSmokeTest.php passed with 13 tests.
- php artisan test tests/Feature/PlatformSuperadminDangerActionsTest.php --filter=impersonation_expiry_logs_audit_end_event passed.

## Explicitly Excluded

This PR intentionally excludes:

- routes/api.php expansion
- new API platform/refund/subscription controllers
- feature/subscription/platform middleware expansion outside web flow
- console command additions
- deploy-staging workflow
- APK binary outputs
- unrelated domain and fixture changes still active in the repository

## Slice Note

There is also a tiny parse-fix in app/Services/TenantDefaultRoleSetupService.php. Keep that as a separate hotfix if you want this PR to stay strictly focused on web-route refactoring.
```

## Notes

- Detail source tetap tersedia di `docs/pr-summary-web-routes-portal-rewrite-2026-04-08.md`.
- Backlog status tetap tersedia di `docs/pending-pr-backlog-2026-04-08.md`.

## Exact Stage List

Jika PR ingin tetap fokus ke web-route rewrite saja, stage hanya file berikut:

- `app/app/Http/Controllers/Web/BackofficePageController.php`
- `app/app/Http/Controllers/Web/LegacyWebRedirectController.php`
- `app/app/Http/Controllers/Web/MenuController.php`
- `app/app/Http/Controllers/Web/OnboardingController.php`
- `app/app/Http/Controllers/Web/PlatformDashboardController.php`
- `app/app/Http/Controllers/Web/PortalAuthController.php`
- `app/app/Http/Controllers/Web/PortalSessionController.php`
- `app/app/Http/Controllers/Web/PosPageController.php`
- `app/app/Http/Controllers/Web/TenantViewController.php`
- `app/app/Services/PortalSwitchService.php`
- `app/app/Services/WebRouteContextService.php`
- `app/routes/web.php`
- `app/docs/pr-summary-web-routes-portal-rewrite-2026-04-08.md`
- `app/docs/pr-ready-web-routes-portal-rewrite-2026-04-08.md`
- `app/docs/commit-message-web-routes-portal-rewrite-2026-04-08.md`

## Keep Out Of This PR

Jangan ikut stage file berikut bila ingin slice tetap bersih:

- `app/app/Services/TenantDefaultRoleSetupService.php`
- `app/docs/pending-pr-backlog-2026-04-08.md`

File pertama adalah hotfix parse kecil yang layak dipisah. File kedua hanya tracker backlog internal dan tidak wajib ikut PR.

## Exact Git Commands

Gunakan command berikut dari repo `app` bila ingin langsung commit slice web yang sekarang sudah staged:

```powershell
git commit -m "refactor(web): move portal and detail page route flow into controllers"
```

Jika perlu re-stage ulang slice web dari awal, gunakan:

```powershell
git add app/Http/Controllers/Web/BackofficePageController.php app/Http/Controllers/Web/LegacyWebRedirectController.php app/Http/Controllers/Web/MenuController.php app/Http/Controllers/Web/OnboardingController.php app/Http/Controllers/Web/PlatformDashboardController.php app/Http/Controllers/Web/PortalAuthController.php app/Http/Controllers/Web/PortalSessionController.php app/Http/Controllers/Web/PosPageController.php app/Http/Controllers/Web/TenantViewController.php app/Services/PortalSwitchService.php app/Services/WebRouteContextService.php routes/web.php docs/pr-summary-web-routes-portal-rewrite-2026-04-08.md docs/pr-ready-web-routes-portal-rewrite-2026-04-08.md docs/commit-message-web-routes-portal-rewrite-2026-04-08.md
```