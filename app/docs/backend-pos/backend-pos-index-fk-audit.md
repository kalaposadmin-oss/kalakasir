# Backend POS - Index & Foreign Key Audit

Tanggal: 2026-03-10

Dokumen ini merangkum audit index dan foreign key berdasarkan file migration di [database/migrations](../../database/migrations). Audit ini bersifat statis (tanpa inspeksi database runtime).

## 1) Ringkasan Foreign Key
- Mayoritas tabel domain memakai `foreignUuid(...)->constrained(...)` ke master utama seperti `tenants`, `branches`, `locations`, `products`, `sales`, dan `purchases`.
- Restrict eksplisit ditemukan pada beberapa tabel transaksi dan referensi penting, misalnya `registers`, `product_variants`, `pricing_rules`, `payments`.
- Cascade eksplisit dipakai untuk tabel yang memang sifatnya turunan langsung (contoh: invoice items pada subscription billing).
- Beberapa FK memakai aturan default database karena tidak menyebutkan `restrictOnDelete` atau `cascadeOnDelete` secara eksplisit.

Catatan:
- FK dengan `nullable()` biasanya dipakai untuk relasi opsional (mis. `customer_id`, `location_id`).
- Seluruh FK utama harus tetap menjaga isolasi tenant.

## 2) Index Tersedia (Migrasi Spesifik)
Berikut index yang ditambahkan melalui migrasi index (tidak termasuk index bawaan dari constraint dan unique keys):

- add_performance_indexes
  - `sales_tenant_status_created_at_idx` pada `sales(tenant_id, status, created_at)`
  - `sales_tenant_branch_created_at_idx` pada `sales(tenant_id, branch_id, created_at)`
  - `stock_ledger_tenant_product_location_idx` pada `stock_ledger(tenant_id, product_id, location_id)`
  - `stock_ledger_tenant_reference_idx` pada `stock_ledger(tenant_id, reference_type, reference_id)`
  - `stock_ledger_tenant_created_at_idx` pada `stock_ledger(tenant_id, created_at)`
  - `audit_logs_tenant_event_idx` pada `audit_logs(tenant_id, event_type)`
  - `audit_logs_tenant_created_at_idx` pada `audit_logs(tenant_id, created_at)`

- add_void_anomaly_detector_indexes
  - `sales_tenant_status_voided_at_idx` pada `sales(tenant_id, status, voided_at)`
  - `sales_tenant_status_voided_by_voided_at_idx` pada `sales(tenant_id, status, voided_by, voided_at)`

- add_pos_indexes
  - `ix_sales_tenant_branch_created_at` pada `sales(tenant_id, branch_id, created_at)`
  - `ix_sale_items_sale_variant` pada `sale_items(sale_id, product_variant_id)`
  - `ix_payments_tenant_sale` pada `payments(tenant_id, sale_id)`
  - `ix_stock_ledger_tenant_location_variant_created_at` pada `stock_ledger(tenant_id, location_id, product_variant_id, created_at)`
  - `ix_inventory_balances_tenant_location_variant` pada `inventory_balances(tenant_id, location_id, product_variant_id)`

## 3) Rekomendasi Verifikasi
- Pastikan semua query list utama memakai pola index yang tersedia (terutama list dengan filter tenant, branch, status, dan rentang waktu).
- Audit query untuk `sales-history`, `inventory-alerts`, dan `stock-card` agar filter utama benar-benar terserve oleh index komposit yang ada.
- Pastikan FK dengan relasi berat transaksi memiliki kebijakan delete yang konsisten (umumnya restrict untuk transaksi inti, cascade hanya untuk tabel pivot/child).

## 4) Catatan Lanjutan
- Jika butuh audit lebih detail, lakukan snapshot schema live dan bandingkan dengan migrasi untuk memastikan index/constraint tidak drift.
- Penambahan index baru sebaiknya berbasis analisis query plan pada data produksi.
