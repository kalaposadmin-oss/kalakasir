# Final Handoff Document — COGS Simulated Validation and Hardening

Tanggal: 2026-04-10

## 1. Executive Summary

Sistem perhitungan biaya penjualan telah diperbaiki secara signifikan dan saat ini siap secara engineering, namun belum tervalidasi secara penuh terhadap data produksi nyata.

Apa yang sudah diperbaiki:

- Perhitungan COGS sekarang berbasis agregasi komponen melalui `sale_cost_components` dan `sales.cogs_total`.
- Sistem tidak lagi mengandalkan pendekatan read path lama yang berisiko menyajikan angka salah tanpa alarm.
- Jika terjadi ketidaksesuaian aggregate, sistem sekarang gagal keras (`fail-loud`) alih-alih mengembalikan hasil yang tampak valid.
- Alur utama penjualan, refund, void, dan produk berbasis BOM telah diuji pada environment lokal terkontrol.

Dampak bisnis:

- Risiko “salah hitung tetapi terlihat benar” sudah ditekan secara signifikan.
- Ketidaksesuaian data kini lebih cepat terdeteksi karena strict reader memblokir state yang tidak valid.
- Struktur laporan keuangan lebih dapat dipercaya secara sistem, walaupun belum production-proven.

Status kesiapan:

- Engineering Ready: ya, dengan readiness sekitar 90%.
- Financially Validated terhadap data production: belum.
- Risiko finansial residual: masih ada sampai data rollout atau production diverifikasi.

Rekomendasi eksekutif:

- Lanjut ke pilot atau staged rollout, bukan full go-live langsung.
- Final sign-off finansial harus menunggu validasi terhadap database rollout atau production yang benar.

## 2. Scope & Context

### Sistem yang Diaudit

Sistem yang diaudit adalah jalur COGS dan reporting yang terkait dengan:

- checkout sale,
- sale dengan BOM,
- refund,
- void,
- finance summary,
- analytics,
- daily finance control,
- daily sales summary,
- valuation method FIFO dan Average.

### Perubahan Utama

Perubahan inti yang menjadi fokus audit adalah model aggregate-only COGS, dengan karakteristik berikut:

- `sale_cost_components` menjadi sumber aggregate cost per sale dan per sale item,
- `sales.cogs_total` menjadi header total yang harus konsisten dengan aggregate component,
- report path normal tidak boleh fallback diam-diam ke snapshot lama,
- sistem harus fail-loud ketika aggregate hilang atau mismatch.

Referensi implementasi utama:

- `app/app/Services/SalesCostAggregationService.php:14` — `captureForSale`
- `app/app/Services/SalesCostAggregationService.php:29` — `recomputeForSale`
- `app/app/Services/SalesCostAggregationService.php:61` — `syncSaleJournalForSale`
- `app/app/Services/SalesCostAggregationService.php:143` — `persistAggregate`

### Batasan Audit

Audit ini tidak menggunakan data rollout atau production. Seluruh pembuktian dilakukan dengan:

- review code,
- SQLite local,
- feature test terfokus,
- command dan endpoint strict reader yang tersedia di environment lokal.

## 3. Key Findings

### Correctness: COGS, BOM, Refund, Void

- Sale non-BOM menghasilkan component cost dasar dan mengisi `sales.cogs_total`.
- Sale BOM menghasilkan base component dan recipe input component, lalu mensinkronkan total COGS ke header sale dan journal.
- Refund mengembalikan stok dan menjaga `refund_items.refunded_cost_total` tetap konsisten dengan reversal valuation.
- Void BOM mengembalikan bahan baku dan mempertahankan pasangan ledger reversal yang lengkap.

Referensi:

- `app/tests/Feature/SalesCostAggregationValidationTest.php:22`
- `app/tests/Feature/SalesCostAggregationValidationTest.php:102`
- `app/tests/Feature/RefundServiceRegressionTest.php:30`
- `app/tests/Feature/VoidServiceInventoryReversalTest.php:71`

### System Integrity: FIFO vs Average

- Pemilihan metode valuation berasal dari tenant valuation method.
- Branch FIFO dan Average terpisah di service valuation, sehingga tidak ada bukti percampuran jalur issue normal.

Referensi:

- `app/app/Services/InventoryValuationService.php:250` — `issueFifo`
- `app/app/Services/InventoryValuationService.php:342` — `issueAverage`
- `app/tests/Feature/ValuationGapCoverageTest.php:115`

### Reporting Correctness

- Finance summary membaca aggregate cost dari subquery aggregate, bukan dari `hpp_snapshot`.
- Daily finance control juga membaca aggregate cost dari subquery aggregate dan memblokir scope invalid.
- Analytics berada di bawah readiness guard yang sama sebelum summary range dibentuk.

Referensi:

- `app/app/Http/Controllers/Api/FinanceReportController.php:41`
- `app/app/Http/Controllers/Api/FinanceReportController.php:51`
- `app/app/Http/Controllers/Api/FinanceReportController.php:79`
- `app/app/Services/DailyFinanceControlService.php:14`
- `app/app/Services/DailyFinanceControlService.php:22`
- `app/app/Services/DailyFinanceControlService.php:32`
- `app/app/Http/Controllers/Api/AnalyticsController.php:38`
- `app/app/Http/Controllers/Api/AnalyticsController.php:218`
- `app/app/Http/Controllers/Api/AnalyticsController.php:219`

### Fail-Loud Behavior

- Read path strict kini tidak hanya menolak sale tanpa aggregate.
- Read path strict juga menolak sale yang memiliki aggregate tetapi `sales.cogs_total` kosong atau mismatch terhadap aggregate component.
- Exception membawa `reason` dan `summary`, sehingga caller menerima konteks kegagalan yang eksplisit.

Referensi:

- `app/app/Services/SalesCostReadinessService.php:39` — `assertSaleHasAggregate`
- `app/app/Services/SalesCostReadinessService.php:56` — `assertNoMissingCompletedSalesForCreatedAtRange`
- `app/app/Services/SalesCostReadinessService.php:79` — `assertNoMissingCompletedSalesForBusinessDateRange`
- `app/app/Services/SalesCostReadinessService.php:126` — `summarizeInvalidSales`
- `app/app/Services/SalesCostReadinessService.php:161` — `summaryReason`
- `app/app/Exceptions/MissingSalesCostAggregateException.php:7`
- `app/app/Exceptions/MissingSalesCostAggregateException.php:33`
- `app/app/Exceptions/MissingSalesCostAggregateException.php:38`

## 4. What Has Been Fixed

| Area | Sebelum | Sesudah |
| --- | --- | --- |
| Sale cost readiness | Read path terutama memblokir sale tanpa aggregate component. | Read path juga memblokir header COGS kosong dan mismatch aggregate. |
| Finance summary | Bisa hanya mengandalkan keberadaan component tanpa memverifikasi integritas header. | Wajib lolos readiness check yang menghitung invalid state, termasuk mismatch. |
| Daily finance control | Read path ketat terhadap aggregate hilang, tetapi integritas header belum dievaluasi sebagai invalid state tersendiri. | Invalid state sekarang ikut mencakup mismatch header dan null header COGS. |
| Daily sales summary apply path | Cukup mensyaratkan aggregate component ada. | Sekarang menolak sale dengan header COGS mismatch. |
| Exception payload | Informasi kegagalan bersifat generik. | Kegagalan sekarang menyertakan `reason`, `summary`, dan sample sale ids. |

Masalah lama ke solusi sekarang:

- Masalah: aggregate ada tetapi `sales.cogs_total` bisa tetap tidak konsisten.
  Solusi: readiness guard diperketat di `SalesCostReadinessService`.
- Masalah: report strict bisa gagal hanya untuk missing component, bukan untuk mismatch integrity.
  Solusi: invalid state sekarang mencakup missing component, missing header, dan mismatched header.
- Masalah: caller hanya tahu “aggregate bermasalah” tanpa klasifikasi.
  Solusi: exception kini memuat alasan dan ringkasan mismatch.

## 5. Validation Evidence

### Test Execution Summary

| Validation Goal | Test / Command | File Reference | Result |
| --- | --- | --- | --- |
| BOM sale writes aggregate and journal sync | `php artisan test tests/Feature/SalesCostAggregationValidationTest.php` | `app/tests/Feature/SalesCostAggregationValidationTest.php:22` | PASS |
| Non-BOM sale writes base component only | `php artisan test tests/Feature/SalesCostAggregationValidationTest.php` | `app/tests/Feature/SalesCostAggregationValidationTest.php:102` | PASS |
| Reader uses aggregate, not snapshot | `php artisan test tests/Feature/SalesCostAggregationValidationTest.php` | `app/tests/Feature/SalesCostAggregationValidationTest.php:150` | PASS |
| Historical rebuild backfills BOM sale and resyncs journal | `php artisan test tests/Feature/SalesCostAggregationValidationTest.php` | `app/tests/Feature/SalesCostAggregationValidationTest.php:247` | PASS |
| Finance strict reader rejects missing aggregate | `php artisan test tests/Feature/SalesCostAggregationValidationTest.php` | `app/tests/Feature/SalesCostAggregationValidationTest.php:461` | PASS |
| Analytics strict reader rejects missing aggregate | `php artisan test tests/Feature/SalesCostAggregationValidationTest.php` | `app/tests/Feature/SalesCostAggregationValidationTest.php:508` | PASS |
| Finance strict reader rejects mismatched header COGS | `php artisan test tests/Feature/SalesCostAggregationValidationTest.php` | `app/tests/Feature/SalesCostAggregationValidationTest.php:555` | PASS |
| Daily sales summary rejects mismatched header COGS | `php artisan test tests/Feature/SalesCostAggregationValidationTest.php` | `app/tests/Feature/SalesCostAggregationValidationTest.php:623` | PASS |
| Refund cost and ledger consistency | `php artisan test tests/Feature/RefundServiceRegressionTest.php --filter=handle_uses_outer_payload_and_ledger_rows_inside_transaction` | `app/tests/Feature/RefundServiceRegressionTest.php:30` | PASS |
| Void BOM reversal integrity | `php artisan test tests/Feature/VoidServiceInventoryReversalTest.php --filter=test_void_sale_dengan_bom_mengembalikan_bahan_baku_dan_membuat_pasangan_ledger_recipe` | `app/tests/Feature/VoidServiceInventoryReversalTest.php:71` | PASS |
| FIFO vs Average isolation in controlled issue case | `php artisan test tests/Feature/ValuationGapCoverageTest.php --filter=fifo_and_average_produce_same_cost_for_single_layer_issue_case` | `app/tests/Feature/ValuationGapCoverageTest.php:115` | PASS |
| Finance control strict reader rejects mismatched header COGS | `php artisan test tests/Feature/FinanceControlDashboardTest.php --filter=rejects_mismatched_header_cogs` | `app/tests/Feature/FinanceControlDashboardTest.php:228` | PASS |

### Evidence Mapping by Claim

| Claim | Evidence |
| --- | --- |
| `sale_cost_components` terbentuk pada sale normal dan BOM | `app/tests/Feature/SalesCostAggregationValidationTest.php:22`, `app/tests/Feature/SalesCostAggregationValidationTest.php:102` |
| `sales.cogs_total` terisi dan disinkronkan ke journal | `app/tests/Feature/SalesCostAggregationValidationTest.php:22`, `app/app/Services/SalesCostAggregationService.php:61` |
| Read path report memakai aggregate, bukan snapshot | `app/tests/Feature/SalesCostAggregationValidationTest.php:150`, `app/app/Http/Controllers/Api/FinanceReportController.php:51` |
| Refund menjaga cost consistency | `app/tests/Feature/RefundServiceRegressionTest.php:30` |
| Void BOM tidak menghilangkan costing trail | `app/tests/Feature/VoidServiceInventoryReversalTest.php:71` |
| Strict reader menolak mismatch header | `app/tests/Feature/SalesCostAggregationValidationTest.php:555`, `app/tests/Feature/FinanceControlDashboardTest.php:228` |

## 6. System Hardening

### Guard yang Ditambahkan

- Guard integritas aggregate di `SalesCostReadinessService` sekarang menghitung tiga kondisi invalid:
  - missing component,
  - missing header COGS,
  - mismatched header COGS.

Referensi:

- `app/app/Services/SalesCostReadinessService.php:126`
- `app/app/Services/SalesCostReadinessService.php:161`

### Fail-Loud Mechanism

- `MissingSalesCostAggregateException` sekarang mengandung `reason` dan `details.summary`.
- Finance summary, analytics, finance control daily, dan daily sales summary apply path tidak lagi hanya memeriksa keberadaan component, tetapi juga integritas header.

Referensi:

- `app/app/Exceptions/MissingSalesCostAggregateException.php:33`
- `app/app/Exceptions/MissingSalesCostAggregateException.php:38`
- `app/app/Http/Controllers/Api/FinanceReportController.php:41`
- `app/app/Services/DailyFinanceControlService.php:22`
- `app/app/Http/Controllers/Api/AnalyticsController.php:38`
- `app/app/Services/SalesCostReadinessService.php:39`

### Perubahan Behavior Sistem

- Sebelumnya, sistem bisa menerima state di mana component aggregate ada tetapi header sale tidak sinkron.
- Sekarang, state tersebut dianggap invalid dan diblokir sebelum report atau summary dibuka.

## 7. Residual Risks

### Engineering Risk

- Jalur aggregate-only utama telah diperkuat dan diuji, tetapi validasi masih terbatas pada SQLite dan skenario terkontrol.
- Replay historis valuation masih memiliki fallback untuk ledger lama, walaupun fallback tersebut tercatat ke audit log.

Referensi fallback terkontrol:

- `app/app/Services/InventoryValuationService.php:1145`
- `app/app/Services/InventoryValuationService.php:1165`

### Financial Risk

- Tidak ada bukti terhadap data rollout atau production nyata.
- Belum ada pembuktian terhadap distribusi mismatch, bentuk data historis live, atau edge case tenant produksi.

### Operational Risk

- Perbedaan semantics waktu antar read path tetap memerlukan disiplin saat final validation live:
  - finance summary berorientasi `created_at`,
  - finance control dan analytics lebih dekat ke business date.
- Tidak ada validasi terhadap volume data tinggi, concurrency, atau drift schema target MySQL rollout.

## 8. Limitations

- Environment validasi yang tersedia adalah SQLite development, bukan database rollout MySQL.
- Audit ini tidak memakai data rollout atau production.
- Audit ini tidak mencakup load test, concurrency test, atau compatibility proof terhadap schema rollout yang belum tersedia.

## 9. Readiness Assessment

| Metric | Status |
| --- | --- |
| Engineering readiness | 90% |
| Financial validation status | Belum tersahkan |
| Safe usage scope | Internal testing, UAT, atau pilot kecil yang terkontrol |
| Production go-live financial sign-off | Belum layak diberikan dari environment ini |

Penjelasan:

- Nilai engineering readiness tinggi karena jalur aggregate-only, strict reader, refund, void, rebuild, dan mismatch hardening sudah terbukti pada test SQLite terfokus.
- Financial validation tetap belum tersahkan karena bukti masih terbatas pada simulated validation dan review code.

## 10. Required Next Steps

Checklist lanjutan yang masih dibutuhkan sebelum financial sign-off:

- [ ] Jalankan `sales-cost:validate` pada database rollout yang benar.
- [ ] Jalankan `sales-cost:rebuild --sync-journal` bila validate menemukan gap.
- [ ] Refresh summary atau materialized report yang terdampak.
- [ ] Lakukan cross-report validation antara finance, analytics, dashboard, dan sales history.
- [ ] Verifikasi bahwa tidak ada mismatch aggregate pada tenant dan rentang tanggal rollout.
- [ ] Berikan final go-live verdict hanya setelah pembuktian terhadap data rollout selesai.

## 11. Final Verdict

### Engineering Verdict

Implementasi aggregate-only COGS berada pada kondisi hardened dan tervalidasi secara teknis untuk environment lokal. Sistem telah diperkuat agar gagal keras ketika aggregate hilang atau tidak konsisten.

### Financial Verdict

Financial correctness belum dapat disahkan untuk go-live karena belum ada pembuktian terhadap data rollout atau production nyata.

### Go-Live Recommendation

Sistem layak dipakai untuk internal testing, UAT, atau pilot kecil yang terkontrol. Sistem belum boleh dianggap `production-validated` untuk go-live finansial penuh sampai validasi terhadap database rollout selesai.

## Appendix A. Executed Test Commands

| Command | Purpose | Result |
| --- | --- | --- |
| `php artisan test tests/Feature/SalesCostAggregationValidationTest.php` | Memvalidasi jalur aggregate-only untuk sale BOM, non-BOM, rebuild, strict reader, dan mismatch handling | PASS |
| `php artisan test tests/Feature/RefundServiceRegressionTest.php --filter=handle_uses_outer_payload_and_ledger_rows_inside_transaction` | Memvalidasi konsistensi refund, valuation reversal, journal, dan ledger | PASS |
| `php artisan test tests/Feature/VoidServiceInventoryReversalTest.php --filter=test_void_sale_dengan_bom_mengembalikan_bahan_baku_dan_membuat_pasangan_ledger_recipe` | Memvalidasi reversal void BOM dan pemulihan bahan baku | PASS |
| `php artisan test tests/Feature/ValuationGapCoverageTest.php --filter=fifo_and_average_produce_same_cost_for_single_layer_issue_case` | Memvalidasi isolasi perilaku FIFO vs Average pada skenario terkendali | PASS |
| `php artisan test tests/Feature/FinanceControlDashboardTest.php --filter=rejects_mismatched_header_cogs` | Memvalidasi fail-loud behavior pada finance control untuk mismatch header COGS | PASS |

## Appendix B. Short Test Outcome Summary

- `SalesCostAggregationValidationTest`: PASS, 9 test lulus.
- `RefundServiceRegressionTest` filtered scenario: PASS, 1 test lulus.
- `VoidServiceInventoryReversalTest` filtered BOM scenario: PASS, 1 test lulus.
- `ValuationGapCoverageTest` filtered FIFO vs Average scenario: PASS, 1 test lulus.
- `FinanceControlDashboardTest` filtered mismatch scenario: PASS, 1 test lulus.