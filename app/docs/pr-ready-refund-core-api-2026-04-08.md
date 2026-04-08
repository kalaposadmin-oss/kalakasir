# PR Ready Refund Core API 2026-04-08

## PR Title

Add core refund API with tenant-safe line-item validation and idempotent create flow

## Commit Message

```text
feat(api): add core refund endpoints with idempotent create flow

Add the core refund API surface for listing, detail, and create flows.

- add tenant-safe refund list/detail endpoints
- add idempotent refund create flow keyed by sale_item_id
- persist refund payment allocation and financial snapshots
- reverse stock, valuation allocation, cash flow, and GL entries in one transaction

Validated with focused refund contract, detail, tenant, service, and variant lifecycle tests.
```

## PR Description

```markdown
## Scope

This PR isolates the core refund API surface and keeps legacy refund audit/remediation out of scope.

## Included Files

- app/Http/Controllers/Api/RefundController.php
- app/Services/RefundService.php
- app/Services/OperationIdempotencyStore.php
- app/Models/Refund.php
- app/Models/RefundItem.php
- app/Support/Decimal.php
- app/Support/ApiResponse.php
- database/migrations/2026_03_09_100100_create_refunds_table.php
- database/migrations/2026_03_09_100110_create_refund_items_table.php
- database/migrations/2026_04_05_120000_harden_refunds_for_financial_reversal.php
- database/migrations/2026_04_05_130000_backfill_legacy_refund_line_item_snapshots.php
- database/migrations/2026_04_07_090000_add_transaction_date_to_refunds_table.php
- refund route lines in routes/api.php

## What Changed

- add GET /api/v1/refunds and GET /api/v1/refunds/{refund}
- add POST /api/v1/refunds with idempotent create flow
- enforce sale_item_id anchored refund validation
- persist refund payment allocations and line-item financial snapshots
- reverse stock, valuation allocations, cash flows, and GL entries atomically

## Validation

- php artisan test tests/Feature/RefundApiContractTest.php passed
- php artisan test tests/Feature/RefundApiDetailTest.php passed
- php artisan test tests/Feature/RefundTenantValidationTest.php passed
- php artisan test tests/Feature/RefundServiceRegressionTest.php passed
- php artisan test tests/Feature/VariantProductInitialStockRefundLifecycleTest.php passed
- combined focused run passed with 18 tests and 201 assertions

## Explicitly Excluded

- refund legacy audit / remediation API
- subscription API
- inventory reporting API
- platform admin API
- unrelated routes/api.php changes outside refund endpoints
```

## Exact Stage List

Stage hanya file berikut jika ingin slice core refund tetap bersih:

- `app/app/Http/Controllers/Api/RefundController.php`
- `app/app/Services/RefundService.php`
- `app/app/Services/OperationIdempotencyStore.php`
- `app/app/Models/Refund.php`
- `app/app/Models/RefundItem.php`
- `app/app/Support/Decimal.php`
- `app/app/Support/ApiResponse.php`
- `app/database/migrations/2026_03_09_100100_create_refunds_table.php`
- `app/database/migrations/2026_03_09_100110_create_refund_items_table.php`
- `app/database/migrations/2026_04_05_120000_harden_refunds_for_financial_reversal.php`
- `app/database/migrations/2026_04_05_130000_backfill_legacy_refund_line_item_snapshots.php`
- `app/database/migrations/2026_04_07_090000_add_transaction_date_to_refunds_table.php`
- refund import + refund route lines di `app/routes/api.php`
- `app/tests/Feature/RefundApiContractTest.php`
- `app/tests/Feature/RefundApiDetailTest.php`
- `app/tests/Feature/RefundTenantValidationTest.php`
- `app/tests/Feature/RefundServiceRegressionTest.php`
- `app/tests/Feature/VariantProductInitialStockRefundLifecycleTest.php`
- `app/docs/pr-summary-refund-core-api-2026-04-08.md`
- `app/docs/pr-ready-refund-core-api-2026-04-08.md`
- `app/docs/commit-message-refund-core-api-2026-04-08.md`

## Keep Out Of This PR

Jangan ikutkan:

- `app/app/Http/Controllers/Api/RefundLegacyAuditController.php`
- `app/app/Services/RefundLegacyAuditService.php`
- route `settings/refund-legacy-audit*` di `app/routes/api.php`
- file subscription, inventory report, dan platform API lainnya

## Exact Git Commands

Core refund butuh partial stage pada `routes/api.php`, jadi jangan pakai `git add routes/api.php` penuh.
Stage file refund lain seperti biasa, lalu stage hunk refund-only untuk `routes/api.php`, kemudian commit:

```powershell
git commit -m "feat(api): add core refund endpoints with idempotent create flow"
```