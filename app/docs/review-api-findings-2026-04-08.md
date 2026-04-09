# Review API Findings 2026-04-08

## Findings

### 1. API auth hardening is a global breaking-behavior change

Severity: tinggi

Lokasi:

- `routes/api.php`

Rincian:

- Seluruh group `/api/v1` sekarang memakai `auth:sanctum` tanpa pengecualian lokal.
- Ini bukan pengetatan kecil di endpoint sensitif saja, tetapi perubahan global pada semua API route yang terdaftar di file ini.

Risiko:

- Client lama, local tools, atau manual QA flow yang sebelumnya tidak mengirim auth akan pecah dengan 401.
- Perubahan ini terlalu luas untuk ikut PR audit observability yang sempit.

### 2. Valuation access now depends on role plus plan/status gating

Severity: tinggi

Lokasi:

- `routes/api.php`
- `app/Http/Middleware/FeatureGateMiddleware.php`
- `app/Http/Middleware/SubscriptionStatusMiddleware.php`

Rincian:

- Endpoint valuation sekarang dijaga oleh kombinasi `role`, `feature_gate`, dan `subscription_status`.
- Artinya behavior akses tidak lagi cukup diuji dengan role matrix saja.

Risiko:

- Regression akan muncul sebagai 403 baru bila plan/status tidak diset dengan benar di fixture atau environment.
- Ini menambah coupling antara subscription state dan inventory/valuation flows.

### 3. Refund surface is no longer just a write endpoint

Severity: tinggi

Lokasi:

- `routes/api.php`
- `app/Http/Controllers/Api/RefundController.php`

Rincian:

- Refund API sekarang mencakup list, detail, create, item aggregation, payment allocation exposure, dan idempotency-backed create flow.
- Ini adalah surface domain baru yang cukup luas.

Risiko:

- Perubahan ini membawa contract baru ke sisi POS/reporting.
- Karena menyentuh payout allocation dan refund history, regression-nya bisa berdampak ke finance trail dan operator flow.

### 4. Inventory report endpoints add reporting logic plus feature checks in controller layer

Severity: sedang

Lokasi:

- `routes/api.php`
- `app/Http/Controllers/Api/InventoryReportController.php`

Rincian:

- Inventory reports ditambah sebagai API surface baru.
- Controller juga melakukan gate tambahan lewat `canUseFeature()` selain role guard di route.

Risiko:

- Ada dua lapis policy: route-level role dan controller-level feature access.
- Jika salah satu berubah tanpa yang lain, perilaku akses bisa membingungkan dan sulit dilacak.

### 5. Subscription API is a full feature batch, not a supporting change

Severity: tinggi

Lokasi:

- `routes/api.php`
- `app/Http/Controllers/Api/SubscriptionController.php`
- `app/Http/Controllers/Api/SubscriptionInvoiceController.php`

Rincian:

- Subscription current, entitlements, impact preview, audit logs, invoices, download invoice, dan mark paid semuanya muncul sebagai API baru.
- Ini menyentuh billing, access, audit, dan admin operations sekaligus.

Risiko:

- Sangat mudah tercampur ke PR yang salah karena route-nya ada di file yang sama.
- Padahal ini seharusnya diperlakukan sebagai batch feature terpisah dengan review domain sendiri.

### 6. Platform admin surface is effectively a separate API subsystem

Severity: tinggi

Lokasi:

- `app/Http/Controllers/Api/Platform/*`
- `app/Http/Requests/Platform/*`

Rincian:

- Terdapat surface tenant, user, invoice, anomaly, audit log, support, settings, payment, plan change, dan dashboard.
- Ini sudah merupakan subsystem platform admin tersendiri.

Risiko:

- Tidak layak ikut terbawa sebagai "sisa kecil" dari audit final.
- Perlu contract review dan auth review tersendiri.

## Minimum API Smoke Checklist

1. Pastikan client yang sah masih lolos `auth:sanctum` di endpoint bootstrap dan checkout.
2. Uji endpoint valuation dengan kombinasi role benar tetapi plan/status salah.
3. Uji refund list/detail/create untuk tenant yang valid dan tenant mismatch.
4. Uji inventory report endpoint dengan feature allowed dan blocked.
5. Uji minimal satu endpoint subscription dan satu endpoint platform admin dengan role yang benar dan salah.