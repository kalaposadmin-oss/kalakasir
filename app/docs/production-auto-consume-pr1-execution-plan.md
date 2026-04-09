# PR-1 Execution Plan: Auto Consume on Sell

Tanggal: 2026-03-13
Status: Ready for implementation
Scope: Kecil, fokus operasional (tanpa engine costing baru)

## 1) Tujuan

Menambahkan auto-consume bahan baku saat transaksi jual untuk produk `finished_good` yang punya resep aktif, dengan tetap memakai satu engine inventory/costing yang sudah ada.

## 2) Prinsip Arsitektur (Tidak Boleh Dilanggar)

- Tidak membuat tabel ledger/costing baru.
- Tetap gunakan:
  - `inventory_balances`
  - `stock_ledger`
  - `InventoryValuationService`
  - `inventory_layers` (FIFO)
  - `inventory_cost_states` (average)
- Retail flow existing tetap berjalan seperti sekarang (no behavior regression).
- Auto-consume hanya menambah workflow operasional di atas engine yang sama.

## 3) Scope PR-1 (In)

- Auto-consume saat checkout untuk `finished_good` dengan recipe aktif.
- Validasi domain recipe:
  - output harus `finished_good`
  - input harus `raw_material`
- Policy stok bahan mentah saat tidak cukup:
  - block default
  - optional allow negative via tenant setting
- Observability minimal event log:
  - `production.auto_consume.started`
  - `production.auto_consume.succeeded`
  - `production.auto_consume.failed`

## 4) Scope PR-1 (Out)

- Waste tracking
- Yield variance
- Production report
- UOM conversion
- Recipe versioning
- Semi-finished workflow

## 5) Data Contract Minimal

Gunakan data produk/BOM yang sudah ada (`products.bom_items`) sebagai recipe level-1 untuk PR-1 ini.

Recipe aktif (PR-1):
- `product.item_type = finished_good`
- `product.product_mode = bom`
- `bom_items` tidak kosong

BOM item minimal:
- `product_id`
- `qty`
- `unit` (dipertahankan sebagai metadata; konversi belum dibahas di PR-1)

## 6) Tenant Settings (Feature Gate)

Tambahkan keys berikut (default aman):
- `auto_consume_on_sell_enabled` = false
- `allow_negative_raw_material_stock` = false

Catatan implementasi:
- Jika setting belum ada, fallback ke `false`.
- Jangan ubah perilaku checkout existing untuk tenant yang flag-nya off.

## 7) Alur Eksekusi Saat Checkout

1. Checkout normal memvalidasi item jual.
2. Untuk setiap line item sale:
   - Jika bukan `finished_good` atau tidak punya recipe aktif: skip auto-consume.
   - Jika punya recipe aktif:
     - Hitung kebutuhan bahan = `bom_qty * sold_qty`.
     - Kurangi `inventory_balances` bahan baku per location sale.
     - Panggil `InventoryValuationService::issue(...)` per bahan.
     - Tulis `stock_ledger` dengan `reference_type = sale_recipe_input`.
3. Jika mode block dan stok bahan tidak cukup:
   - batalkan checkout dengan 422.
4. Jika mode allow negative:
   - tetap lanjut, raise anomaly `negative_stock_attempt` dengan source `auto_consume_sale`.

## 8) Error Contract (PR-1)

Kode error usulan:
- `AUTO_CONSUME_DISABLED`
- `AUTO_CONSUME_RECIPE_INVALID`
- `AUTO_CONSUME_INSUFFICIENT_RAW_MATERIAL`

Payload pattern tetap mengikuti format error API existing.

## 9) Target File Perubahan (Estimasi)

Core/service:
- `app/app/Services/CheckoutService.php`
- `app/app/Services/InventoryValuationService.php` (tanpa ubah kontrak publik; hanya reuse)
- `app/app/Services/AnomalyService.php` (reuse only)
- `app/app/Services/ProductItemTypeMapper.php` (reuse)

Support/gate:
- `app/config/platform.php` atau `app/config/feature-gating.json` (sesuai pola repo)
- `app/app/Support/FeatureGateConfig.php` (jika dipakai di repo ini)

Tests:
- `app/tests/Feature/AutoConsumeOnSellTest.php` (baru)
- `app/tests/Feature/CheckoutTransactionDateValidationTest.php` (regression guard jika perlu)
- `app/tests/Feature/WriteFlowTransactionDateHappyPathTest.php` (regression guard jika perlu)

Catatan:
- Jangan ubah flow retail existing di test lama kecuali untuk menambah assertion no-regression.

## 10) Test Matrix PR-1 (Wajib)

### A. Happy Path

1. `finished_good` dengan recipe aktif, flag ON, stok cukup:
- checkout sukses
- bahan baku berkurang di `inventory_balances`
- ada `stock_ledger` baris `sale_recipe_input`
- valuation issue tercatat (allocation/cost state/layer sesuai metode tenant)

2. mixed cart (produk biasa + produk recipe):
- produk biasa tetap flow lama
- produk recipe trigger auto-consume

### B. Negative Path

1. recipe output bukan `finished_good` -> 422 `AUTO_CONSUME_RECIPE_INVALID`
2. komponen recipe bukan `raw_material` -> 422 `AUTO_CONSUME_RECIPE_INVALID`
3. stok bahan kurang + allow negative OFF -> 422 `AUTO_CONSUME_INSUFFICIENT_RAW_MATERIAL`
4. stok bahan kurang + allow negative ON -> checkout tetap sukses, anomaly tercatat

### C. Idempotency / Safety

1. request checkout retry dengan idempotency key yang sama:
- auto-consume tidak double issue
- ledger `sale_recipe_input` tidak dobel

### D. No Regression

1. checkout produk non-recipe (retail biasa) tetap hijau.
2. test write-flow existing tetap hijau.

## 11) Observability Contract

Untuk setiap transaksi yang trigger auto-consume, log context minimal:
- `tenant_id`
- `sale_id`
- `location_id`
- `product_id`
- `raw_material_product_id`
- `raw_material_qty`
- `unit_cost`
- `total_cost`
- `status` (`started|succeeded|failed`)
- `error` (jika gagal)

## 12) Checklist Review PR-1

- [ ] Tidak ada tabel engine costing baru.
- [ ] Hanya tambah workflow auto-consume.
- [ ] Semua issue bahan lewat `InventoryValuationService::issue`.
- [ ] `stock_ledger` tercatat konsisten untuk auto-consume.
- [ ] `inventory_cost_states`/`inventory_layers` ter-update via engine existing.
- [ ] Feature flag OFF = perilaku checkout lama tetap sama.
- [ ] Semua test PR-1 + regression lulus.

## 13) Rollout Aman

- Tahap 1: enable per tenant pilot (food/minuman kecil).
- Tahap 2: monitor anomaly dan selisih stok 3-7 hari.
- Tahap 3: enable bertahap tenant berikutnya.

## 14) Catatan Implementasi

- Prioritaskan kejelasan error message untuk user UMKM.
- Hindari istilah teknis engine di surface API/UI.
- Pertahankan bahasa operasional: bahan baku, hasil jual, biaya bahan.
