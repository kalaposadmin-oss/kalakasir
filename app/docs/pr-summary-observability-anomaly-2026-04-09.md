# PR Summary Observability Anomaly 2026-04-09

## Scope

PR ini hanya membawa observability dan anomaly surface yang tenant-safe: filter API per cabang/lokasi/reference, audit resolve anomaly, dan penyegaran halaman observability untuk batch variance. Jobs detector, listener, dan perubahan catalog lain tetap di luar scope.

## Included Files

- `app/Http/Controllers/Api/ObservabilityController.php`
- `app/Http/Controllers/Api/AnomalyController.php`
- `resources/views/observability.blade.php`
- observability/anomalies route lines di `routes/api.php`
- `tests/Feature/AnomalyObservabilityApiTest.php`
- `tests/Feature/ObservabilityTenantScopingTest.php`

## What Changed

- meredaksi metrik queue global pada health endpoint dan mempertahankan analytics anomaly yang tetap tenant-scoped
- menambahkan filter branch/location pada observability health dan anomaly list
- menambahkan filter `reference_id` untuk anomaly sehingga batch variance bisa dilacak langsung dari referensi batch
- mencatat audit log saat anomaly di-resolve
- memperluas halaman observability dengan scope filter, quick filter production batch variance, dan context state di query string

## Validation

- `php artisan test tests/Feature/AnomalyObservabilityApiTest.php tests/Feature/ObservabilityTenantScopingTest.php` passed
- `php artisan test tests/Feature/WebSettingsFinanceSmokeTest.php --filter=observability` passed
- `php artisan test tests/Feature/PortalWebAccessTest.php --filter=observability` passed

## Explicitly Excluded

- detector jobs dan queue-monitoring jobs
- listener/event observability plumbing di luar API resolve flow
- catalog, discount, loyalty, dan register work