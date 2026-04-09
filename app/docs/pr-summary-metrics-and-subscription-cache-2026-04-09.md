# PR Summary - Metrics and Subscription Cache Wiring 2026-04-09

## Ringkasan

Batch ini menambahkan service agregasi yang sudah dipakai controller analytics, finance summary, dan sales history, lalu menutup wiring listener saat plan subscription berubah.

## Cakupan

- `AnalyticsMetricsService` untuk trend, top product, payment mix, dan summary analytics tenant
- `FinanceGlReportService` untuk ringkasan GL, expense/revenue breakdown, dan balance sheet dasar
- `SalesHistoryMetricsService` untuk agregat sales history, payment mix, refund netting, dan top product per filter
- `HandleSubscriptionPlanChanged` untuk invalidasi cache entitlement plan saat perubahan paket
- registrasi listener di `AppServiceProvider`
- test tenant scoping dan refund-netting yang memukul service ini secara langsung

## Nilai Perubahan

- controller yang sebelumnya sudah mengandalkan service ini sekarang punya implementasi konkret
- agregat analytics, finance, dan sales history menjadi tenant-scoped dan konsisten dengan refund behavior
- perubahan paket subscription tidak lagi menyisakan cache entitlement lama

## Validasi

- `php artisan test tests/Feature/AnalyticsTenantScopingTest.php tests/Feature/FinanceReportRefundNettingTest.php tests/Feature/SalesHistoryTenantScopingTest.php`