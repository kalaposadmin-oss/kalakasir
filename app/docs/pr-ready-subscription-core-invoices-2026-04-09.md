# PR Ready Subscription Core Invoices 2026-04-09

## PR Title

Add subscription core and invoice endpoints

## Commit Message

```text
feat(api): add subscription core and invoice endpoints

Add the tenant-facing subscription and billing API surface.

- add current plan, entitlements, impact preview, and audit log endpoints
- add idempotent subscription plan change flow with scheduled downgrade support
- add invoice list, detail, download, and mark-paid endpoints with tenant scoping
- add subscription audit/billing schema and feature gate config source of truth

Validated with focused subscription plan, core API, and invoice scoping tests.
```

## PR Description

```markdown
## Scope

This PR isolates the tenant-facing subscription core and invoice surface. It intentionally keeps middleware hardening, platform admin subscription exports, inventory reporting, and broader route/api changes out of scope.

## Included Files

- app/Http/Controllers/Api/SubscriptionController.php
- app/Http/Controllers/Api/SubscriptionInvoiceController.php
- app/Services/SubscriptionService.php
- app/Events/SubscriptionPlanChanged.php
- app/Support/FeatureGateConfig.php
- config/feature-gating.json
- database/migrations/2026_03_05_000100_add_plan_to_tenants_and_create_subscription_audit_logs_table.php
- database/migrations/2026_03_05_001200_harden_subscription_lifecycle_and_billing_tables.php
- database/migrations/2026_03_05_001300_add_subscription_plan_change_schedule_columns.php
- tests/Feature/SubscriptionPlanMvpTest.php
- tests/Feature/SubscriptionCoreApiTest.php
- tests/Feature/SubscriptionInvoiceTenantScopingTest.php
- subscription import + route lines in routes/api.php

## What Changed

- add subscription current, entitlements, impact preview, and audit log endpoints
- add idempotent plan change flow with immediate upgrades and scheduled downgrades
- add invoice list/detail/download and mark-paid endpoints with tenant-safe scoping
- persist subscription audit logs, subscription operations, invoices, invoice items, and subscription payments schema
- introduce feature gate config lookup for plan labels and entitlement matrix

## Validation

- php artisan test tests/Feature/SubscriptionPlanMvpTest.php passed
- php artisan test tests/Feature/SubscriptionCoreApiTest.php passed
- php artisan test tests/Feature/SubscriptionInvoiceTenantScopingTest.php passed
- combined focused run passed with 16 tests and 89 assertions

## Explicitly Excluded

- SubscriptionModuleHardeningTest.php and broader feature-gate/status hardening assertions
- FeatureGateMiddleware.php and SubscriptionStatusMiddleware.php
- bootstrap/app.php middleware alias wiring
- platform subscription export routes
- inventory reporting API
- platform admin API
- middleware dependency pass
```

## Exact Stage List

Stage hanya file berikut jika ingin slice subscription tetap bersih:

- `app/app/Http/Controllers/Api/SubscriptionController.php`
- `app/app/Http/Controllers/Api/SubscriptionInvoiceController.php`
- `app/app/Services/SubscriptionService.php`
- `app/app/Events/SubscriptionPlanChanged.php`
- `app/app/Support/FeatureGateConfig.php`
- `app/config/feature-gating.json`
- `app/database/migrations/2026_03_05_000100_add_plan_to_tenants_and_create_subscription_audit_logs_table.php`
- `app/database/migrations/2026_03_05_001200_harden_subscription_lifecycle_and_billing_tables.php`
- `app/database/migrations/2026_03_05_001300_add_subscription_plan_change_schedule_columns.php`
- `app/tests/Feature/SubscriptionPlanMvpTest.php`
- `app/tests/Feature/SubscriptionCoreApiTest.php`
- `app/tests/Feature/SubscriptionInvoiceTenantScopingTest.php`
- hunk import `SubscriptionController`, `SubscriptionInvoiceController`, dan route `subscription/*` di `app/routes/api.php`
- `app/docs/pr-summary-subscription-core-invoices-2026-04-09.md`
- `app/docs/pr-ready-subscription-core-invoices-2026-04-09.md`
- `app/docs/commit-message-subscription-core-invoices-2026-04-09.md`

## Keep Out Of This PR

Jangan ikutkan:

- `app/tests/Feature/SubscriptionModuleHardeningTest.php`
- `app/app/Http/Middleware/FeatureGateMiddleware.php`
- `app/app/Http/Middleware/SubscriptionStatusMiddleware.php`
- `app/bootstrap/app.php`
- route valuation gating atau inventory report di `app/routes/api.php`
- route refund legacy audit di `app/routes/api.php`
- route platform subscription export di route platform
- file platform admin, inventory reporting, dan middleware lain

## Exact Git Commands

Gunakan stage biasa untuk file penuh berikut:

```powershell
git add app/app/Http/Controllers/Api/SubscriptionController.php app/app/Http/Controllers/Api/SubscriptionInvoiceController.php app/app/Services/SubscriptionService.php app/app/Events/SubscriptionPlanChanged.php app/app/Support/FeatureGateConfig.php app/config/feature-gating.json app/database/migrations/2026_03_05_000100_add_plan_to_tenants_and_create_subscription_audit_logs_table.php app/database/migrations/2026_03_05_001200_harden_subscription_lifecycle_and_billing_tables.php app/database/migrations/2026_03_05_001300_add_subscription_plan_change_schedule_columns.php app/tests/Feature/SubscriptionPlanMvpTest.php app/tests/Feature/SubscriptionCoreApiTest.php app/tests/Feature/SubscriptionInvoiceTenantScopingTest.php app/docs/pr-summary-subscription-core-invoices-2026-04-09.md app/docs/pr-ready-subscription-core-invoices-2026-04-09.md app/docs/commit-message-subscription-core-invoices-2026-04-09.md
```

Untuk `app/routes/api.php`, stage hanya hunk import/controller subscription dan route `subscription/*`.

Setelah itu commit dengan:

```powershell
git commit -m "feat(api): add subscription core and invoice endpoints"
```