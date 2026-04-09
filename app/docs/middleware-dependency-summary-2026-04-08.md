# Middleware Dependency Summary 2026-04-08

## Tujuan

Memetakan dependency runtime antara batch residual route/API baru dan middleware yang sekarang sudah aktif di bootstrap.

## Global Middleware yang Sudah Aktif

- `SecurityHeadersMiddleware`
- `TenantResolver`
- `PreventWritesDuringValuationRebuild`
- `RequestContextMiddleware`

Implikasi:

- Semua route web dan API baru sudah dipengaruhi layer ini tanpa perlu mendeklarasikan middleware di route satu per satu.

## Route Alias yang Menjadi Dependency Inti

- `role`
- `feature_gate`
- `canonical_portal_host`
- `portal`
- `subscription_status`
- `web_access`

## Dependency Map

### 1. `routes/web.php` -> `canonical_portal_host` + `web_access`

Dipakai oleh:

- login portal POS/backoffice
- group route `/pos/*`
- group route `/backoffice/*`

Konsekuensi:

- Batch web routing tidak bisa direview sendirian tanpa middleware portal/access.

### 2. `routes/api.php` -> `role` + `feature_gate` + `subscription_status`

Dipakai oleh:

- finance control
- observability health
- valuation reports dan rebuild
- subscription endpoints
- refund legacy audit

Konsekuensi:

- Batch API residual tidak bisa direview hanya dari route/controller; middleware access policy adalah bagian dari contract.

### 3. Semua API tenant-aware -> `TenantResolver`

Dipakai secara implisit oleh:

- refund APIs
- subscription APIs
- inventory report APIs
- valuation APIs
- checkout/finance APIs

Konsekuensi:

- Tenant mismatch handling adalah dependency lintas surface, bukan detail kecil di satu feature.

### 4. Semua HTML/web response -> `SecurityHeadersMiddleware`

Dipakai secara implisit oleh:

- login pages
- onboarding pages
- POS/backoffice pages
- legacy redirected pages yang berujung ke HTML render

Konsekuensi:

- Review route web baru harus selalu mempertimbangkan CSP/nonces dan asset loading.

### 5. Portal switching and impersonation flows -> `WebAccessMiddleware`

Dependency tidak langsung:

- role normalization
- subscription feature access
- session `auth_portal`
- portal host resolver

Konsekuensi:

- Regression portal jarang berdiri sendiri; biasanya terjadi karena kombinasi route + session + middleware.

## Rekomendasi Review Batch

1. Batch `web.php` direview bersama `WebAccessMiddleware`, `CanonicalPortalHostMiddleware`, `SecurityHeadersMiddleware`, dan `WebPortalResolver`.
2. Batch `api.php` direview bersama `RoleMiddleware`, `TenantResolver`, `FeatureGateMiddleware`, dan `SubscriptionStatusMiddleware`.
3. Surface subscription/platform jangan dipisahkan dari middleware access/gating saat review.

## Practical Rule

Kalau sebuah route baru bergantung pada salah satu alias di atas, maka middleware terkait harus dianggap satu paket review walau file-nya berada di batch berbeda.