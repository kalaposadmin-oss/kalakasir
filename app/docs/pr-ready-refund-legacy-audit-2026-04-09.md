# PR Ready Refund Legacy Audit 2026-04-09

## PR Title

Add refund legacy audit API and safe remediation commands

## Commit Message

```text
feat(api): add refund legacy audit and remediation surface

Add the refund legacy audit API and operational remediation commands.

- add audit, history, export, and remediation endpoints for legacy refund rows
- add console commands to inspect, export, and backfill historical refund_items
- persist remediation audit logs and CSV export support
- harden remediation matching to respect product_variant_id when present

Validated with focused refund legacy audit API, command, and web smoke tests.
```

## PR Description

```markdown
## Scope

This PR isolates the refund legacy audit and remediation surface on top of the already separated refund core baseline. It keeps subscription, inventory reporting, platform admin, and middleware expansion out of scope.

## Included Files

- app/Http/Controllers/Api/RefundLegacyAuditController.php
- app/Services/RefundLegacyAuditService.php
- tests/Feature/RefundLegacyAuditApiTest.php
- tests/Feature/RefundLegacyAuditCommandTest.php
- tests/Feature/RefundLegacyAuditWebSmokeTest.php
- refund legacy audit import + route lines in routes/api.php
- refund legacy audit import + command blocks in routes/console.php

## What Changed

- add refund legacy audit list, history, export, and remediation endpoints
- add console commands for audit, export, and safe remediation of historical refund_items with missing sale_item_id
- classify historical rows as resolvable, ambiguous, or orphan
- write remediation audit logs and export remediation history CSV
- harden sale item matching to use product_variant_id when present so remediation stays tenant-safe and variant-safe

## Validation

- php artisan test tests/Feature/RefundLegacyAuditApiTest.php passed
- php artisan test tests/Feature/RefundLegacyAuditCommandTest.php passed
- php artisan test tests/Feature/RefundLegacyAuditWebSmokeTest.php passed
- combined focused run passed with 16 tests and 130 assertions

## Explicitly Excluded

- InventoryReportController and inventory reporting routes
- SubscriptionController and SubscriptionInvoiceController routes
- other routes/api.php role and gate expansions
- pos:audit-consistency command in routes/console.php
- valuation:backfill-purchase-layer-source-items command in routes/console.php
- platform admin API
- middleware dependency pass
```

## Exact Stage List

Stage hanya file berikut jika ingin slice refund legacy audit tetap bersih:

- `app/app/Http/Controllers/Api/RefundLegacyAuditController.php`
- `app/app/Services/RefundLegacyAuditService.php`
- `app/tests/Feature/RefundLegacyAuditApiTest.php`
- `app/tests/Feature/RefundLegacyAuditCommandTest.php`
- `app/tests/Feature/RefundLegacyAuditWebSmokeTest.php`
- hunk import `RefundLegacyAuditController` + lima route `settings/refund-legacy-audit*` di `app/routes/api.php`
- hunk import `RefundLegacyAuditService` + tiga command `refunds:audit-legacy-ambiguity`, `refunds:export-legacy-ambiguity`, `refunds:remediate-legacy-sale-item` di `app/routes/console.php`
- `app/docs/pr-summary-refund-legacy-audit-2026-04-09.md`
- `app/docs/pr-ready-refund-legacy-audit-2026-04-09.md`
- `app/docs/commit-message-refund-legacy-audit-2026-04-09.md`

## Keep Out Of This PR

Jangan ikutkan:

- import `InventoryReportController`, `SubscriptionController`, `SubscriptionInvoiceController` di `app/routes/api.php`
- perubahan auth/global role/gate lain di `app/routes/api.php`
- import `PurchaseHistoryBackfillService` di `app/routes/console.php`
- command `pos:audit-consistency` di `app/routes/console.php`
- command `valuation:backfill-purchase-layer-source-items` di `app/routes/console.php`
- file inventory reporting, subscription, platform, dan middleware lainnya

## Exact Git Commands

Gunakan stage biasa untuk file baru berikut:

```powershell
git add app/app/Http/Controllers/Api/RefundLegacyAuditController.php app/app/Services/RefundLegacyAuditService.php app/tests/Feature/RefundLegacyAuditApiTest.php app/tests/Feature/RefundLegacyAuditCommandTest.php app/tests/Feature/RefundLegacyAuditWebSmokeTest.php app/docs/pr-summary-refund-legacy-audit-2026-04-09.md app/docs/pr-ready-refund-legacy-audit-2026-04-09.md app/docs/commit-message-refund-legacy-audit-2026-04-09.md
```

Untuk `app/routes/api.php` dan `app/routes/console.php`, stage hanya hunk refund legacy audit lewat Source Control hunk staging agar perubahan lain tetap di luar slice.

Setelah itu commit dengan:

```powershell
git commit -m "feat(api): add refund legacy audit and remediation surface"
```