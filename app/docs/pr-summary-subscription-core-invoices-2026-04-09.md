# PR Summary Subscription Core Invoices 2026-04-09

## Scope

PR ini hanya membawa subscription core tenant-facing API dan invoice surface. Platform subscription export route, middleware dependency pass, inventory reporting, dan platform admin subsystem tetap di luar scope.

## Included Files

- `app/Http/Controllers/Api/SubscriptionController.php`
- `app/Http/Controllers/Api/SubscriptionInvoiceController.php`
- `app/Services/SubscriptionService.php`
- `app/Events/SubscriptionPlanChanged.php`
- `app/Support/FeatureGateConfig.php`
- `config/feature-gating.json`
- `database/migrations/2026_03_05_000100_add_plan_to_tenants_and_create_subscription_audit_logs_table.php`
- `database/migrations/2026_03_05_001200_harden_subscription_lifecycle_and_billing_tables.php`
- `database/migrations/2026_03_05_001300_add_subscription_plan_change_schedule_columns.php`
- `tests/Feature/SubscriptionPlanMvpTest.php`
- `tests/Feature/SubscriptionCoreApiTest.php`
- `tests/Feature/SubscriptionInvoiceTenantScopingTest.php`
- subscription import + route lines di `routes/api.php`

## What Changed

### Subscription Read API

- menambahkan endpoint `GET /api/v1/subscription/current`
- menambahkan endpoint `GET /api/v1/subscription/entitlements`
- menambahkan endpoint `GET /api/v1/subscription/impact-preview`
- menambahkan endpoint `GET /api/v1/subscription/audit-logs`
- menambahkan endpoint `GET /api/v1/subscription/audit-logs/export`

### Subscription Plan Change

- menambahkan endpoint `PATCH /api/v1/subscription/plan`
- mendukung idempotency key untuk perubahan plan
- mendukung immediate upgrade dan scheduled downgrade `end_of_period`
- menyimpan audit log perubahan plan dan metadata request

### Subscription Invoices

- menambahkan endpoint `GET /api/v1/subscription/invoices`
- menambahkan endpoint `GET /api/v1/subscription/invoices/{invoice}`
- menambahkan endpoint `GET /api/v1/subscription/invoices/{invoice}/download`
- menambahkan endpoint `POST /api/v1/subscription/invoices/{invoice}/mark-paid`
- menjaga tenant scoping invoice/payment dan reaktivasi subscription saat overdue invoice lunas

### Feature Gate Source of Truth

- menambahkan `FeatureGateConfig` dan `config/feature-gating.json` sebagai sumber label plan dan entitlement matrix
- subscription service sekarang menjadi basis plan/status/preview/proration untuk tenant-facing flows

## Validation

- `php artisan test tests/Feature/SubscriptionPlanMvpTest.php` passed
- `php artisan test tests/Feature/SubscriptionCoreApiTest.php` passed
- `php artisan test tests/Feature/SubscriptionInvoiceTenantScopingTest.php` passed
- combined focused run: `16` tests passed, `89` assertions

## Explicitly Excluded

PR ini sengaja tidak membawa:

- `tests/Feature/SubscriptionModuleHardeningTest.php`
- `app/Http/Middleware/FeatureGateMiddleware.php`
- `app/Http/Middleware/SubscriptionStatusMiddleware.php`
- bootstrap alias wiring di `bootstrap/app.php`
- route platform subscription export
- inventory reporting API
- platform admin API
- middleware dependency pass

## Staging Note

- `routes/api.php` mengandung banyak perubahan lain, jadi stage hanya import `SubscriptionController`, `SubscriptionInvoiceController`, dan route `subscription/*`
- jangan ikutkan valuation gating, inventory reporting, refund legacy audit, atau role expansion lain dari `routes/api.php`