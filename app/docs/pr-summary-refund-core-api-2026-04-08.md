# PR Summary Refund Core API 2026-04-08

## Scope

PR ini hanya membawa core refund API untuk list, detail, dan create refund. Legacy audit/remediation refund sengaja tidak ikut agar batch tetap kecil.

## Included Files

- `app/Http/Controllers/Api/RefundController.php`
- `app/Services/RefundService.php`
- `app/Services/OperationIdempotencyStore.php`
- `app/Models/Refund.php`
- `app/Models/RefundItem.php`
- `app/Support/Decimal.php`
- `app/Support/ApiResponse.php`
- `database/migrations/2026_03_09_100100_create_refunds_table.php`
- `database/migrations/2026_03_09_100110_create_refund_items_table.php`
- `database/migrations/2026_04_05_120000_harden_refunds_for_financial_reversal.php`
- `database/migrations/2026_04_05_130000_backfill_legacy_refund_line_item_snapshots.php`
- `database/migrations/2026_04_07_090000_add_transaction_date_to_refunds_table.php`
- refund route lines di `routes/api.php`

## What Changed

### Refund API

- menambahkan endpoint `GET /api/v1/refunds`
- menambahkan endpoint `GET /api/v1/refunds/{refund}`
- menambahkan endpoint `POST /api/v1/refunds`

### Refund Execution

- refund sekarang memakai `sale_item_id` sebagai anchor line item
- payout refund mendukung idempotency key cache via `OperationIdempotencyStore`
- refund memulihkan stok, ledger, allocation reversal, cash flow, dan journal reversal dalam satu transaksi
- refund menyimpan financial snapshot per line item termasuk gross, item discount, transaction discount, tax, net, dan refunded cost total

### Tenant Safety

- validasi request memastikan sale, register, product, dan payment method tetap berada di tenant yang benar
- create flow menolak over-refund dan mismatch `sale_item_id` / `product_id` / `product_variant_id`

## Validation

- `php artisan test tests/Feature/RefundApiContractTest.php` passed
- `php artisan test tests/Feature/RefundApiDetailTest.php` passed
- `php artisan test tests/Feature/RefundTenantValidationTest.php` passed
- `php artisan test tests/Feature/RefundServiceRegressionTest.php` passed
- `php artisan test tests/Feature/VariantProductInitialStockRefundLifecycleTest.php` passed
- combined focused run: `18` tests passed, `201` assertions

## Explicitly Excluded

PR ini sengaja tidak membawa:

- `app/Http/Controllers/Api/RefundLegacyAuditController.php`
- `app/Services/RefundLegacyAuditService.php`
- route `settings/refund-legacy-audit*`
- subscription API
- inventory reporting API
- platform admin API
- middleware gate expansion lain di `routes/api.php`