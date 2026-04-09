# PR Ready - Metrics and Subscription Cache Wiring 2026-04-09

## Judul PR

`feat(app): add analytics finance sales metrics services`

## Fokus Reviewer

- pastikan seluruh query agregat tetap tenant-scoped
- pastikan refund netting mengurangi revenue dan tax secara konsisten, bukan hanya menambah baris breakdown
- pastikan service sales history menghitung summary dan payment mix dari filter yang sama dengan list endpoint
- pastikan listener plan change benar-benar terdaftar dan hanya menginvalidasi entitlement cache yang relevan

## Validasi

- `AnalyticsTenantScopingTest`
- `FinanceReportRefundNettingTest`
- `SalesHistoryTenantScopingTest`