# Paket Langganan Kalakasir - Hasil Akhir Implementasi

Tanggal: 2026-04-11
Status: final sesuai codebase Laravel saat ini
Scope: perbedaan paket `S`, `M`, `P` berdasarkan feature gate, route middleware, controller guard, dan UI web yang aktif di repo

Dokumen ini menjelaskan kondisi runtime yang benar-benar berlaku saat ini, bukan proposal paket lama.

Sumber utama:
- [config/feature-gating.json](../config/feature-gating.json)
- [app/Support/FeatureGateConfig.php](../app/Support/FeatureGateConfig.php)
- [app/Services/SubscriptionService.php](../app/Services/SubscriptionService.php)
- [routes/api.php](../routes/api.php)
- [routes/web.php](../routes/web.php)
- [app/Http/Controllers/Api/ProductController.php](../app/Http/Controllers/Api/ProductController.php)
- [resources/views/product-create.blade.php](../resources/views/product-create.blade.php)
- [resources/views/product-edit.blade.php](../resources/views/product-edit.blade.php)

## 1. Ringkasan Singkat

Kode paket aktif di sistem:
- `S` = `Starter`
- `M` = `Medium`
- `P` = `Pro`

Perbedaan paling penting setelah implementasi terbaru:
- Paket `S` tidak bisa memakai `materials`
- Paket `S` tidak bisa memakai `recipe / BOM`
- Paket `S` tidak bisa memakai `production batches`
- Paket `M` sudah bisa memakai `materials`, `recipe / BOM`, dan `production batches`
- Paket `P` berisi semua paket `M` ditambah fitur kontrol, audit, valuation, observability, dan integrasi tingkat lanjut

## 2. Hasil Akhir Per Paket

### Paket S - Starter

Paket ini cocok untuk operasional dasar toko.

Fitur yang aktif di paket `S`:
- onboarding
- dashboard overview dan insight dasar
- POS checkout
- hold bill
- split payment
- refund
- void
- buka shift
- tutup shift
- riwayat shift
- riwayat penjualan
- riwayat pembayaran
- riwayat stok
- ringkasan laporan
- analitik produk
- laporan inventory ringkas
- laba rugi dasar
- daftar produk
- kategori produk
- pricing produk dasar
- inventory stock
- stock adjustment
- stock transfer
- purchase order
- receiving pembelian
- stock purchase
- supplier manage
- store profile
- printer receipt
- devices
- payments
- subscription billing

Fitur yang tidak aktif di paket `S`:
- pencarian global
- notification list
- POS customer
- print summary shift
- analitik pembayaran
- laporan pembelian lanjutan
- export PDF
- export CSV
- materials
- recipe / BOM management
- production batches
- purchase return
- customer management
- loyalty
- settings tax
- promo default
- staff basic
- branch management
- notifications setting
- dashboard control
- history audit log
- stock card
- FIFO layers
- balance sheet
- cash flow
- lock period
- product valuation
- COA
- roles and permissions
- multi branch advanced
- inventory valuation settings
- observability
- integrations

Khusus implementasi baru untuk paket `S`:
- route web `/backoffice/materials` ditolak
- route web `/backoffice/inventory/production-batches` ditolak
- API `/api/v1/materials` ditolak
- API `/api/v1/production-batches` ditolak
- create/update product dengan `BOM`, `auto_consume_on_sell`, atau `batch_production` ditolak
- form create/edit product menyembunyikan editor BOM untuk tenant `S`

### Paket M - Medium

Paket ini adalah paket minimum untuk operasional yang sudah mulai kompleks.

Semua fitur paket `S`, ditambah:
- pencarian global
- notification list
- POS customer
- print summary shift
- analitik pembayaran
- laporan pembelian
- export PDF
- export CSV
- materials
- recipe / BOM management
- production batches
- purchase return
- customer management
- loyalty
- settings tax
- promo default
- staff basic
- branch management
- notifications setting

Artinya paket `M` sudah bisa:
- mengelola bahan baku di halaman materials
- membuat produk dengan BOM / recipe
- mengaktifkan auto-consume on sell
- mengaktifkan batch production pada finished good
- membuka, mengedit, mem-posting, dan membatalkan production batch

Fitur yang masih belum aktif di paket `M`:
- dashboard control
- history audit log
- stock card
- FIFO layers
- balance sheet
- cash flow
- lock period
- product valuation
- COA
- roles and permissions
- multi branch advanced
- inventory valuation settings
- observability
- integrations

### Paket P - Pro

Paket ini adalah paket tertinggi dan mencakup semua fitur paket `M`.

Tambahan utama paket `P` dibanding `M`:
- dashboard control
- history audit log
- stock card
- FIFO layers
- balance sheet
- cash flow
- lock period
- product valuation
- COA
- roles and permissions
- multi branch advanced
- inventory valuation settings
- observability
- integrations

Secara praktis paket `P` dipakai untuk:
- kontrol keuangan lebih lengkap
- audit trail dan observability
- valuation dan inventory control lanjutan
- kontrol akses dan struktur organisasi yang lebih kompleks
- integrasi sistem eksternal

## 3. Tabel Perbedaan Inti

| Area | Starter `S` | Medium `M` | Pro `P` |
|---|---|---|---|
| POS dasar | Ya | Ya | Ya |
| POS customer | Tidak | Ya | Ya |
| Shift print summary | Tidak | Ya | Ya |
| Produk dan kategori | Ya | Ya | Ya |
| Materials | Tidak | Ya | Ya |
| Recipe / BOM | Tidak | Ya | Ya |
| Production batches | Tidak | Ya | Ya |
| Purchase return | Tidak | Ya | Ya |
| Customer management | Tidak | Ya | Ya |
| Loyalty | Tidak | Ya | Ya |
| Export PDF / CSV | Tidak | Ya | Ya |
| Stock card | Tidak | Tidak | Ya |
| FIFO layers | Tidak | Tidak | Ya |
| Balance sheet | Tidak | Tidak | Ya |
| Cash flow | Tidak | Tidak | Ya |
| Lock period | Tidak | Tidak | Ya |
| Product valuation | Tidak | Tidak | Ya |
| Observability | Tidak | Tidak | Ya |
| Integrations | Tidak | Tidak | Ya |

## 4. Fitur Yang Sekarang Wajib Minimal Paket M

Setelah implementasi terbaru, fitur berikut resmi menjadi minimal paket `M`:

### 4.1 Materials

Yang dibatasi:
- halaman web materials
- list materials API
- detail materials API
- create materials API
- update materials API
- delete materials API

Gate yang dipakai:
- `product.materials`

Lokasi implementasi:
- [routes/api.php](../routes/api.php)
- [routes/web.php](../routes/web.php)

### 4.2 Recipe / BOM

Yang dibatasi:
- create product dengan `product_mode = bom`
- update product yang mengubah atau mempertahankan recipe-managed configuration
- penggunaan `production_mode = auto_consume_on_sell`
- penggunaan `production_mode = batch_production`
- editor BOM di form create product
- editor BOM di form edit product

Gate yang dipakai:
- `product.recipe_management`

Lokasi implementasi:
- [app/Http/Controllers/Api/ProductController.php](../app/Http/Controllers/Api/ProductController.php)
- [resources/views/product-create.blade.php](../resources/views/product-create.blade.php)
- [resources/views/product-edit.blade.php](../resources/views/product-edit.blade.php)

### 4.3 Production Batches

Yang dibatasi:
- list production batches
- detail production batch
- export problematic production batches
- create production batch
- update production batch
- post production batch
- cancel production batch
- halaman web daftar batch produksi
- halaman web detail batch produksi

Gate yang dipakai:
- `inventory.production_batches`

Lokasi implementasi:
- [routes/api.php](../routes/api.php)
- [routes/web.php](../routes/web.php)

## 5. Perilaku UI Untuk Tenant Paket S

Perilaku akhir di web untuk tenant `S`:
- menu / halaman materials tidak bisa dibuka
- menu / halaman production batch tidak bisa dibuka
- di create product, user melihat notice bahwa BOM / recipe hanya tersedia mulai paket `M`
- editor BOM disembunyikan
- di edit product, jika produk memakai recipe atau production mode, user melihat notice bahwa perubahan butuh paket `M`
- submit perubahan resep ditahan dari UI agar tidak berujung error 403 setelah klik simpan

Ini berarti pembatasan sekarang berlaku di dua lapis:
- server-side gate
- client-side / halaman web guard

## 6. Perilaku Status Subscription Di Atas Paket

Selain plan `S/M/P`, ada status subscription yang juga memengaruhi akses.

Status yang terlihat aktif di sistem:
- `active`
- `trialing`
- `past_due`
- `suspended`
- `canceled`

Catatan penting:
- sebagian fitur premium juga dibatasi lagi oleh `subscription_status` middleware
- contoh kuatnya ada di valuation endpoints
- jadi plan `P` saja tidak selalu cukup jika status subscription tidak valid

Implikasi praktis:
- plan menentukan entitlement fitur
- status subscription menentukan apakah entitlement itu boleh dipakai saat ini

## 7. Ringkasan Sederhana Untuk Bisnis

### Starter `S`

Dipakai untuk:
- jualan
- stok dasar
- pembelian dasar
- laporan dasar

Tidak dapat:
- bahan baku terpisah
- resep / BOM
- produksi
- customer, loyalty, retur pembelian
- export laporan
- akuntansi dan kontrol lanjutan

### Medium `M`

Dipakai untuk:
- semua kebutuhan Starter
- bahan baku
- resep / BOM
- produksi
- retur pembelian
- customer
- loyalty
- export laporan
- pengaturan operasional yang lebih rapi

Ini paket minimum untuk usaha yang:
- mulai mengelola bahan baku
- ingin auto-consume saat jual
- ingin batch production

### Pro `P`

Dipakai untuk:
- semua kebutuhan Medium
- kontrol akuntansi lebih lengkap
- observability
- audit dan governance
- valuation lanjutan
- integrasi

Ini paket untuk usaha yang sudah butuh:
- kontrol internal lebih kuat
- laporan dan governance lebih lengkap
- monitoring sistem dan audit trail

## 8. Kesimpulan Final

Kesimpulan runtime saat ini:
- `S` = operasional dasar, tanpa materials, recipe, dan production
- `M` = operasional menengah, termasuk materials, recipe, dan production
- `P` = operasional + kontrol profesional, audit, valuation, observability, integrasi

Keputusan implementasi terbaru yang paling penting:
- fitur `materials`, `recipe / BOM`, dan `production batches` sekarang resmi berada di minimal paket `M`
- paket `S` sudah tidak punya akses baik dari API maupun halaman web
- UI produk juga sudah disesuaikan agar tenant `S` tidak melihat editor recipe seolah fitur itu tersedia
