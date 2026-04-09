# PR Ready Inventory Reporting 2026-04-09

## PR Title

Add inventory reporting endpoints

## Commit Message

```text
feat(api): add inventory reporting endpoints

Add the inventory reporting API surface for summary, exports, and stock card reporting.

- add inventory summary and low-stock endpoints
- add CSV and PDF export for inventory reporting
- add stock-card CSV export with tenant-safe location filtering
- include operational audit trail and tenant-safe reporting coverage

Validated with focused inventory reporting and valuation authority tests.
```

## PR Description

```markdown
## Scope

This PR isolates the inventory reporting API surface. It intentionally keeps middleware feature/status gate wiring, platform admin routes, and unrelated API role changes out of scope.

## Included Files

- app/Http/Controllers/Api/InventoryReportController.php
- tests/Feature/InventoryOperationalReportsTest.php
- inventory import + route lines in routes/api.php

## What Changed

- add inventory summary, low-stock, CSV export, PDF export, and stock-card CSV export endpoints
- compute reporting metrics from tenant-safe inventory, ledger, anomaly, and valuation data
- include operational audit trail summaries in reporting output
- keep export output aligned with summary response data

## Validation

- php artisan test tests/Feature/InventoryOperationalReportsTest.php passed
- php artisan test tests/Feature/InventoryValueAuthorityTest.php passed
- combined focused run passed with 11 tests and 99 assertions

## Explicitly Excluded

- FeatureGateMiddleware and SubscriptionStatusMiddleware
- valuation route gating changes in routes/api.php
- platform admin API
- middleware dependency pass
- commands and deploy workflow
```

## Exact Stage List

Stage hanya file berikut jika ingin slice inventory reporting tetap bersih:

- `app/app/Http/Controllers/Api/InventoryReportController.php`
- `app/tests/Feature/InventoryOperationalReportsTest.php`
- hunk import `InventoryReportController` + route `reports/inventory/summary`, `reports/inventory/low-stock`, `reports/inventory/export/csv`, `reports/inventory/export/pdf`, `reports/inventory/stock-card/{product}/export/csv` di `app/routes/api.php`
- `app/docs/pr-summary-inventory-reporting-2026-04-09.md`
- `app/docs/pr-ready-inventory-reporting-2026-04-09.md`
- `app/docs/commit-message-inventory-reporting-2026-04-09.md`

## Keep Out Of This PR

Jangan ikutkan:

- inventory alert role change di `app/routes/api.php`
- production batch export route di `app/routes/api.php`
- valuation route gating with `feature_gate` and `subscription_status`
- platform admin API
- middleware files dan `bootstrap/app.php`

## Exact Git Commands

Gunakan stage biasa untuk file penuh berikut:

```powershell
git add app/app/Http/Controllers/Api/InventoryReportController.php app/tests/Feature/InventoryOperationalReportsTest.php app/docs/pr-summary-inventory-reporting-2026-04-09.md app/docs/pr-ready-inventory-reporting-2026-04-09.md app/docs/commit-message-inventory-reporting-2026-04-09.md
```

Untuk `app/routes/api.php`, stage hanya hunk import/controller inventory report dan route `reports/inventory/*`.

Setelah itu commit dengan:

```powershell
git commit -m "feat(api): add inventory reporting endpoints"
```