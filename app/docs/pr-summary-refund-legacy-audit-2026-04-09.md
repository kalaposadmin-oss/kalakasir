# PR Summary Refund Legacy Audit 2026-04-09

## Scope

PR ini hanya membawa refund legacy audit/remediation surface sebagai lanjutan kecil di atas baseline refund core (`48558ab`, `d634cd1`). Subscription, inventory reporting, platform API, dan middleware expansion sengaja tetap di luar scope.

## Included Files

- `app/Http/Controllers/Api/RefundLegacyAuditController.php`
- `app/Services/RefundLegacyAuditService.php`
- `tests/Feature/RefundLegacyAuditApiTest.php`
- `tests/Feature/RefundLegacyAuditCommandTest.php`
- `tests/Feature/RefundLegacyAuditWebSmokeTest.php`
- refund legacy audit route lines di `routes/api.php`
- refund legacy audit command blocks di `routes/console.php`

## What Changed

### Audit API

- menambahkan endpoint `GET /api/v1/settings/refund-legacy-audit`
- menambahkan endpoint `GET /api/v1/settings/refund-legacy-audit/history`
- menambahkan endpoint `GET /api/v1/settings/refund-legacy-audit/history/export`
- menambahkan endpoint `GET /api/v1/settings/refund-legacy-audit/export`
- menambahkan endpoint `POST /api/v1/settings/refund-legacy-audit/remediate`

### Audit Classification

- mengaudit historical `refund_items` yang masih kosong `sale_item_id`
- mengelompokkan row menjadi `resolvable_single_match`, `ambiguous_multi_match`, dan `orphan_no_match`
- menyediakan summary, pagination, filter cabang/tanggal, dan export CSV

### Remediation Safety

- remediation hanya meng-update row yang benar-benar resolvable
- audit log remediation tersimpan per updated row
- matching sale item sekarang variant-aware memakai `product_variant_id` bila tersedia, sehingga historical refund row tidak salah dipasangkan saat satu produk punya beberapa variant dalam sale yang sama

### Operational Commands

- menambahkan `refunds:audit-legacy-ambiguity`
- menambahkan `refunds:export-legacy-ambiguity`
- menambahkan `refunds:remediate-legacy-sale-item`

## Validation

- `php artisan test tests/Feature/RefundLegacyAuditApiTest.php` passed
- `php artisan test tests/Feature/RefundLegacyAuditCommandTest.php` passed
- `php artisan test tests/Feature/RefundLegacyAuditWebSmokeTest.php` passed
- combined focused run: `16` tests passed, `130` assertions

## Explicitly Excluded

PR ini sengaja tidak membawa:

- `app/Http/Controllers/Api/InventoryReportController.php`
- `app/Http/Controllers/Api/SubscriptionController.php`
- `app/Http/Controllers/Api/SubscriptionInvoiceController.php`
- route selain refund legacy audit di `routes/api.php`
- command `pos:audit-consistency` di `routes/console.php`
- command `valuation:backfill-purchase-layer-source-items` di `routes/console.php`
- platform admin API
- middleware access / feature gate / subscription gate pass

## Staging Note

- `routes/api.php` mengandung banyak perubahan lain, jadi stage hanya import `RefundLegacyAuditController` dan lima route `settings/refund-legacy-audit*`
- `routes/console.php` juga bercampur dengan command lain, jadi stage hanya import `RefundLegacyAuditService` dan tiga command `refunds:*legacy*`
- `RefundLegacyAuditWebSmokeTest` memvalidasi page existing di web layer, tetapi `routes/web.php` dan `resources/views/refund-legacy-audit.blade.php` tidak muncul sebagai active diff slice ini