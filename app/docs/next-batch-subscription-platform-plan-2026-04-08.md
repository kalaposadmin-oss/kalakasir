# Next Batch Subscription Platform Plan 2026-04-08

## Tujuan

Menangani residual batch subscription, refund, inventory report, dan platform admin sebagai pekerjaan terpisah setelah PR audit final dipisahkan.

## Scope Batch Berikutnya

1. Subscription public tenant-facing API.
2. Subscription invoice and mark-paid flow.
3. Refund and refund legacy audit flow.
4. Inventory reporting API surface.
5. Platform admin API surface.
6. Middleware gating yang menjadi dependency aksesnya.

## Urutan Review

### 1. Subscription Core

Fokus file:

- `app/Http/Controllers/Api/SubscriptionController.php`
- `app/Http/Controllers/Api/SubscriptionInvoiceController.php`

Checklist:

- plan and status read flow
- impact preview
- audit log export
- invoice list/detail/download
- mark-paid idempotency and permission path

### 2. Refund Surface

Fokus file:

- `app/Http/Controllers/Api/RefundController.php`
- `app/Http/Controllers/Api/RefundLegacyAuditController.php`

Checklist:

- refund list/detail response contract
- refund creation idempotency
- payout allocation exposure
- legacy remediation safety and audit trail

### 3. Inventory Reporting Surface

Fokus file:

- `app/Http/Controllers/Api/InventoryReportController.php`

Checklist:

- summary/low-stock/export behavior
- duplicated access control between route and controller
- feature gate behavior on blocked tenants

### 4. Platform Admin API

Fokus file:

- `app/Http/Controllers/Api/Platform/*`
- `app/Http/Requests/Platform/*`
- `app/Services/Platform/*`

Checklist:

- superadmin enforcement
- pagination/filter contracts
- idempotent action endpoints
- export endpoints

### 5. Middleware Dependency Pass

Fokus file:

- `app/Http/Middleware/RoleMiddleware.php`
- `app/Http/Middleware/TenantResolver.php`
- `app/Http/Middleware/FeatureGateMiddleware.php`
- `app/Http/Middleware/SubscriptionStatusMiddleware.php`

Checklist:

- tenant mismatch behavior
- normalized role behavior
- feature gate 403 contract
- subscription status gate 403 contract

## Output yang Diharapkan

1. Daftar file yang bisa dipisahkan sebagai PR subscription/refund/platform.
2. Temuan prioritas tinggi untuk breaking behavior.
3. Daftar smoke/API tests minimum untuk tiap surface.
4. Rekomendasi pemisahan batch agar tidak tercampur dengan route web rewrite.

## Updated Execution Note

- Setelah commit audit final `2b20228`, sub-batch API expansion paling kecil yang layak diambil lebih dulu adalah refund surface.
- Alasan: `RefundController.php` (`352` baris) + `RefundLegacyAuditController.php` (`251` baris) jauh lebih kecil dibanding `InventoryReportController.php` (`1661` baris).
- Status terbaru: refund core sudah punya baseline commit (`48558ab`, `d634cd1`), sedangkan refund legacy audit sudah implemented di worktree dan lolos validasi `RefundLegacyAuditApiTest`, `RefundLegacyAuditCommandTest`, dan `RefundLegacyAuditWebSmokeTest`.
- Hardening terbaru: remediation refund legacy sekarang match `sale_items` secara variant-aware memakai `product_variant_id` bila tersedia, jadi row historis dengan produk yang sama tetapi variant berbeda tidak salah diklasifikasikan sebagai ambiguous atau salah dipasangkan saat backfill.
- Urutan eksekusi praktis terbaru:
	1. refund legacy audit selesai di `7bc4553`
	2. subscription core + invoices selesai di `b0d8590`
	3. inventory reporting selesai di `b7e6cec`
	4. platform admin API menjadi slice aktif berikutnya
	5. middleware dependency pass sesudah platform
	6. commands/deploy workflow paling akhir