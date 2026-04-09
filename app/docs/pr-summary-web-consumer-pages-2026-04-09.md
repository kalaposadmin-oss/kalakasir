# PR Summary - Web Consumer Pages 2026-04-09

## Ringkasan

Batch ini menambahkan halaman consumer backoffice yang berjalan di atas fondasi portal yang sudah dikomit sebelumnya.

Halaman yang masuk:

- subscription current, change, features, history, invoices
- settings preferences dan integrations
- help center, about, support
- marketing online store dan referral

## Karakter Batch

- hanya berisi Blade views
- memakai guard role yang konsisten dengan portal backoffice
- memanfaatkan bootstrap API atau endpoint yang sudah lebih dulu tersedia
- tidak membawa halaman platform admin dan refund legacy audit

## Validasi

- `php artisan test tests/Feature/WebSubscriptionSupportSmokeTest.php tests/Feature/WebSupportMarketingSmokeTest.php`