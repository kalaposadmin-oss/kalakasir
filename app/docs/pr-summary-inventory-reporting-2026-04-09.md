# PR Summary Inventory Reporting 2026-04-09

## Scope

PR ini hanya membawa inventory reporting API surface. Middleware gating, valuation access hardening, platform admin API, dan route/api expansion lain tetap di luar scope.

## Included Files

- `app/Http/Controllers/Api/InventoryReportController.php`
- `tests/Feature/InventoryOperationalReportsTest.php`
- inventory report import + route lines di `routes/api.php`

## What Changed

### Reporting Endpoints

- menambahkan endpoint `GET /api/v1/reports/inventory/summary`
- menambahkan endpoint `GET /api/v1/reports/inventory/low-stock`
- menambahkan endpoint `GET /api/v1/reports/inventory/export/csv`
- menambahkan endpoint `GET /api/v1/reports/inventory/export/pdf`
- menambahkan endpoint `GET /api/v1/reports/inventory/stock-card/{product}/export/csv`

### Reporting Behavior

- summary menyatukan KPI stok, nilai stok, movement range, audit trail operasional, dan low-stock preview
- export CSV/PDF memakai sumber summary yang sama agar angka ringkasan tetap konsisten
- stock-card export mendukung range dan lokasi dengan fallback lokasi default tenant
- reporting menjaga tenant scoping untuk movement, anomaly, export, dan low-stock data

## Validation

- `php artisan test tests/Feature/InventoryOperationalReportsTest.php` passed
- `php artisan test tests/Feature/InventoryValueAuthorityTest.php` passed
- combined focused run: `11` tests passed, `99` assertions

## Explicitly Excluded

PR ini sengaja tidak membawa:

- `FeatureGateMiddleware.php`
- `SubscriptionStatusMiddleware.php`
- valuation route gating changes di `routes/api.php`
- inventory alert role expansion di `routes/api.php`
- production batch export route di `routes/api.php`
- platform admin API
- middleware dependency pass

## Staging Note

- `routes/api.php` mengandung banyak perubahan lain, jadi stage hanya import `InventoryReportController` dan lima route `reports/inventory/*`
- `InventoryValueAuthorityTest.php` dipakai untuk validasi tapi tidak perlu ikut commit bila tidak muncul sebagai active diff