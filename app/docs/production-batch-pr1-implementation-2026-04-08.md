# Implementasi PR 1 Batch Production

Tanggal: 2026-04-08
Status: Implemented dan tervalidasi
Scope: explicit production mode, draft batch, posting actual yield, skip auto-consume untuk batch production

## Ringkasan

PR 1 menambahkan mode produksi eksplisit di level produk agar flow produksi tidak lagi bergantung pada inferensi dari `product_mode` saja.

Setelah perubahan ini:

- `production_mode = none` berarti produk tidak ikut workflow produksi otomatis.
- `production_mode = auto_consume_on_sell` berarti bahan baku dikonsumsi saat checkout sale.
- `production_mode = batch_production` berarti bahan baku tidak dikonsumsi saat sale, tetapi saat batch diposting.

Tujuan utamanya adalah memisahkan dua workflow yang sebelumnya berpotensi bentrok:

- finished good yang langsung mengonsumsi bahan saat dijual
- finished good yang harus diproduksi dulu dan hanya menjual stok jadi

## Keputusan Domain

### 1. Produk finished good sekarang punya kontrak produksi yang eksplisit

Field baru `products.production_mode` dipakai sebagai guard utama untuk menentukan perilaku operasional.

Kontrak yang berlaku:

- `batch_production` hanya valid untuk produk `finished_good` yang `track_stock = true`
- `auto_consume_on_sell` tetap memakai resep dari `bom_items`
- `batch_production` tetap boleh memakai resep dari `bom_items`, tetapi konsumsi bahan terjadi saat `production-batches/{id}/post`

### 2. Auto consume dibuat fail-closed

`AutoConsumeOnSellService` sekarang hanya berjalan bila `production_mode === auto_consume_on_sell`.

Implikasi:

- produk `batch_production` tidak akan double-consume saat checkout
- produk legacy yang tidak mengirim `production_mode` tetap aman karena default `none`

### 3. Sale hanya mengurangi finished good untuk mode batch production

Checkout untuk produk `batch_production` hanya bergerak di stok barang jadi.
Issue bahan baku dan costing output dilakukan saat posting batch produksi.

## Perubahan Schema

### products

- tambah `production_mode`
- default `none`
- backfill produk legacy `product_mode = bom` menjadi `auto_consume_on_sell`

### production_batches

Header batch sekarang menyimpan data operasional yang dibutuhkan untuk actual yield:

- `branch_id`
- `product_id`
- `product_variant_id`
- `recipe_version`
- `expected_output_qty`
- `actual_output_qty`
- `yield_variance_qty`
- `yield_variance_pct`
- `variance_reason`
- `idempotency_key`
- audit timestamp tambahan

### production_batch_items

Line item batch sekarang menyimpan planned vs actual:

- `planned_qty`
- `actual_qty`
- `uom`
- timestamp line item

Catatan migrasi:

- migrasi extension item dibuat defensif karena `product_variant_id` sudah lebih dulu ada dari migrasi lain

## Perubahan API

## Produk

Endpoint create/update product sekarang menerima dan memvalidasi `production_mode`.

Aturan validasi penting:

- `batch_production` tidak boleh dipakai untuk service/non-stock item
- payload lama yang tidak mengirim `production_mode` tetap sukses dan default ke `none`

## Production Batch

### List history

`GET /api/v1/production-batches`

Query filter yang didukung:

- `limit`
- `product_id`
- `status`
- `cancel_reason`
- `has_open_anomaly`
- `branch_id`
- `location_id`
- `transaction_date_from`
- `transaction_date_to`

Perilaku:

- hasil tetap tenant-scoped
- batch yang punya anomaly `production_batch_yield_variance` terbuka sekarang membawa `anomaly_summary` dan `observability_url`
- detail batch sekarang juga membawa `anomaly_history` agar UI bisa menampilkan open/resolved alert per batch
- list bisa dipersempit ke draft/posted/cancelled yang alasan pembatalannya cocok, atau hanya batch dengan alert terbuka
- list mendukung `sort_by = newest | oldest | largest_variance | latest_anomaly`
- response `meta.kpis` merangkum total batch, draft, posted, cancelled, open alert, jumlah batch bervariance, dan total qty variance pada hasil yang sedang aktif
- response `meta.filters` mengembalikan filter yang benar-benar dipakai
- `transaction_date` pada list dinormalisasi ke format `Y-m-d` untuk kebutuhan monitoring/history

### Create draft

`POST /api/v1/production-batches`

Payload utama:

- `branch_id`
- `location_id`
- `product_id`
- `product_variant_id` opsional
- `transaction_date`
- `expected_output_qty`
- `recipe_version`
- `reference_no` opsional

Perilaku:

- hanya menerima produk dengan `production_mode = batch_production`
- recipe dibaca dari `products.bom_items`
- server membentuk snapshot line input dan line output draft

### Update draft

`PATCH /api/v1/production-batches/{batch}`

Payload utama:

- `transaction_date` opsional
- `expected_output_qty` opsional
- `recipe_version` opsional
- `reference_no` opsional
- `notes` opsional
- `product_variant_id` opsional
- `inputs[]` opsional dengan `product_id`, `product_variant_id` opsional, `planned_qty`

Perilaku:

- hanya bisa untuk batch `draft`
- jika `inputs` tidak dikirim dan `expected_output_qty` berubah, planned qty input di-rescale proporsional dari snapshot draft yang ada
- jika `inputs` dikirim, key input harus persis sama dengan snapshot draft
- line output draft ikut diperbarui mengikuti `expected_output_qty`

### Cancel draft

`POST /api/v1/production-batches/{batch}/cancel`

Payload utama:

- `cancel_reason` wajib

Perilaku:

- hanya bisa untuk batch `draft`
- `cancel_reason` wajib diisi dan disimpan di header batch untuk jejak operasional
- batch `posted` atau `cancelled` tidak bisa diubah lagi lewat endpoint draft
- foreign batch tetap tersembunyi dengan kontrak `404`

### Post batch

`POST /api/v1/production-batches/{batch}/post`

Header wajib:

- `Idempotency-Key`

Payload utama:

- `actual_output_qty`
- `variance_reason` opsional
- `inputs[]` dengan `product_id`, `product_variant_id` opsional, `actual_qty`

Perilaku:

- idempotent per batch + idempotency key
- issue bahan baku via `InventoryValuationService::issue(...)`
- receive finished good via `InventoryValuationService::receive(...)`
- menulis `stock_ledger` dengan `reference_type = production_batch`
- menghitung `yield_variance_qty` dan `yield_variance_pct`
- `variance_reason` wajib diisi bila variance actual yield menyentuh threshold monitoring anomaly

## Audit Log

Operasi batch berikut sekarang menulis `audit_logs` dengan `reference_type = production_batch`:

- `production_batch.created`
- `production_batch.updated`
- `production_batch.cancelled`
- `production_batch.posted`

Payload audit menyimpan snapshot operasional yang cukup untuk review cepat:

- field header penting batch
- before/after snapshot untuk update draft
- idempotency key dan hasil aktual saat post
- actor, tenant, dan reference batch

Idempotent replay saat post tidak membuat audit log duplikat.

## Monitoring Variance

Post batch sekarang juga bisa menaikkan anomaly operasional bila variance hasil produksi terlalu besar.

Konfigurasi threshold ada di `config/services.php`:

- `services.anomaly.production_batch_yield_variance_pct_threshold`
- `services.anomaly.production_batch_yield_variance_qty_threshold`
- `services.anomaly.production_batch_yield_variance_severity`

Perilaku:

- anomaly type: `production_batch_yield_variance`
- dipicu setelah batch berhasil `posted`
- payload anomaly menyimpan batch, produk, lokasi, output expected vs actual, variance qty/pct, reason, dan threshold aktif
- idempotent replay saat post tidak membuat anomaly duplikat
- threshold yang sama juga dipakai untuk mewajibkan operator mengisi `variance_reason` pada saat post

Ini memberi jalur monitoring cepat untuk batch yang yield-nya terlalu jauh dari rencana tanpa mengubah kontrak posting utama.

## Web Backoffice

Flow web dasar untuk batch production sekarang tersedia di backoffice:

- `GET /backoffice/inventory/production-batches`
- `GET /backoffice/inventory/production-batches/{batch}`

Kemampuan yang sudah tersedia di web:

- list batch dengan filter produk, status, cancel reason, open alert, cabang, lokasi, tanggal
- detail batch untuk header, input, output, variance, dan indikator alert anomaly terbuka
- create draft batch dari halaman list
- edit draft batch dari halaman detail
- cancel draft batch dari halaman detail
- post batch dari halaman detail dengan input aktual dan idempotency key client-side
- shortcut dari detail batch ke halaman observability yang sudah terfilter per `reference_id` batch
- kartu list menyorot `cancel_reason` untuk batch cancelled dan badge open alert untuk batch yang masih punya anomaly aktif

## Observability dan Konsistensi Audit

Peningkatan lanjutan setelah kontrak draft/post/cancel stabil:

- endpoint anomaly sekarang mendukung filter `reference_id` agar halaman observability bisa dibuka langsung ke konteks satu batch
- endpoint anomaly juga mendukung filter `branch_id` dan `location_id` berbasis payload anomaly untuk investigasi operasional yang lebih sempit
- resolve anomaly tenant-level sekarang menulis `audit_logs` dengan event `anomaly_resolved`
- detail batch sekarang mendukung filter history `all | open | resolved` dan load-more berbasis kontrak API `anomaly_status` + `anomaly_limit`
- resolve alert di detail batch sekarang memakai modal khusus agar catatan resolve tidak lagi bergantung pada browser prompt/confirm
- export CSV batch bermasalah tersedia lewat endpoint `GET /api/v1/production-batches/export/problematic` dengan filter yang mengikuti list aktif
- payload audit create/update/post/cancel disejajarkan untuk selalu membawa reason operasional yang relevan (`variance_reason` / `cancel_reason`)
- detail batch dan history list sekarang membaca summary anomaly yang sama dari API sehingga status batch, reason, dan alert terbuka tetap konsisten di UI dan API
- audit trail laporan operasional inventaris sekarang memperkaya anomaly `production_batch_yield_variance` dengan referensi batch, cabang, lokasi, output aktual, variance, reason, dan resolve note

## Operasional Backoffice

Panduan ringkas untuk operator dan reviewer:

- isi `variance_reason` saat hasil aktual menyentuh threshold monitoring yield variance
- isi `cancel_reason` saat draft dibatalkan agar histori produksi tetap bisa diaudit
- gunakan badge open alert di list batch untuk prioritas review cepat
- dari detail batch, admin/owner bisa melihat riwayat alert, memfilter open/resolved, memuat history tambahan, dan menutup anomaly yang sudah diverifikasi
- dari list batch, reviewer bisa lompat langsung ke observability per batch dan mengunduh CSV batch bermasalah sesuai filter aktif
- halaman observability sekarang bisa difilter per batch, cabang, dan lokasi untuk mempercepat investigasi

Wrapper legacy juga tersedia:

- `/production-batches`
- `/production-batches/{batch}`

## Error Contract

Perbaikan tambahan di create endpoint:

- invalid transaction date sekarang mengembalikan `422`
- code: `INVALID_TRANSACTION_DATE`

Ini menjaga konsistensi dengan endpoint tulis lain yang sudah memakai kontrak validasi tanggal transaksi.

## Dampak ke Void dan Checkout

Karena guard `production_mode` menjadi lebih ketat, fixture dan test yang masih mengandalkan `product_mode = bom` tanpa `production_mode` harus disejajarkan.

Contoh regresi yang ditutup saat verifikasi:

- test void BOM awalnya gagal karena fixture tidak lagi memicu auto-consume
- fixture diperbarui agar produk BOM auto-consume memakai `production_mode = auto_consume_on_sell`

Ini penting karena kontrak domain baru adalah:

- `product_mode = bom` saja tidak cukup untuk memicu konsumsi bahan
- perilaku operasional ditentukan oleh `production_mode`

## Guard Domain Tambahan

- create draft sekarang menolak produk `batch_production` yang nonaktif
- post batch sekarang menolak draft jika produk header sudah menjadi nonaktif
- endpoint draft update/cancel hanya berlaku untuk batch `draft`
- record batch tenant lain tetap tersembunyi pada endpoint update/cancel/post

## Verifikasi

Verifikasi terbaru setelah penambahan anomaly variance:

- focused suite: `php artisan test tests/Feature/ProductionBatchQuickFlowTest.php`
- full suite: `php artisan test`

Status terbaru full suite: lulus, termasuk regression untuk:

- `ProductionBatchQuickFlowTest`
- `ProductionBatchTenantScopingTest`
- `PortalWebAccessTest`
- `AnomalyObservabilityApiTest`

## File Inti yang Berubah

- `app/app/Models/Product.php`
- `app/app/Models/ProductionBatch.php`
- `app/app/Models/ProductionBatchItem.php`
- `app/app/Services/ProductItemTypeValidator.php`
- `app/app/Services/AutoConsumeOnSellService.php`
- `app/app/Services/ProductionBatchService.php`
- `app/app/Http/Controllers/Api/ProductController.php`
- `app/app/Http/Controllers/Api/ProductionBatchController.php`
- migrasi `2026_04_08_130000`, `130100`, `130200`

## Verifikasi

Suite fokus yang dipakai selama implementasi:

- `tests/Feature/ProductionBatchQuickFlowTest.php`
- `tests/Feature/ProductionBatchTenantScopingTest.php`
- `tests/Feature/AutoConsumeOnSellCheckoutFlowTest.php`
- `tests/Feature/ProductItemTypeCatalogApiTest.php`
- `tests/Feature/VoidServiceInventoryReversalTest.php`

Hasil akhir verifikasi:

- suite fokus batch production hijau
- suite filter history alert, export batch bermasalah, dan audit trail inventory report hijau
- suite filter list/history batch hijau
- suite audit batch production hijau
- suite smoke/access web batch production hijau
- suite void reversal dan auto-consume hijau
- full suite Laravel hijau

Ringkasan terakhir:

- `php artisan test` -> full suite hijau

## Catatan Lanjutan

PR 1 ini belum mencakup:

- waste tracking
- semi-finished workflow
- multi-level production routing
- recipe versioning historis per transaksi
- UOM conversion
- production reporting

Langkah berikut yang aman adalah membangun workflow operasional di atas kontrak ini, bukan menambah engine inventory/costing baru.