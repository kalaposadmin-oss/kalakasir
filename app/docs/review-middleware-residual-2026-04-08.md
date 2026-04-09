# Review Middleware Residual 2026-04-08

## Kesimpulan

Residual middleware bukan batch kecil. Middleware baru sudah terhubung di `bootstrap/app.php` dan sekarang menjadi fondasi untuk route web dan API yang baru. Ini harus direview sebagai layer runtime inti, bukan hanya sebagai file pendukung.

## Wiring yang Sudah Aktif

Temuan utama dari `bootstrap/app.php`:

- `SecurityHeadersMiddleware` di-append global.
- `TenantResolver` di-append global.
- Alias middleware baru sudah didaftarkan: `feature_gate`, `canonical_portal_host`, `portal`, `subscription_status`, dan `web_access`.
- `RoleMiddleware` juga tetap aktif sebagai alias utama untuk authorization API.

Implikasi:

- Ini bukan middleware yang menganggur; ia sudah mempengaruhi request runtime.
- Kesalahan kecil pada salah satu middleware bisa berdampak lintas aplikasi.

## Perubahan Utama

### 1. Tenant Resolver Menjadi Lebih Ketat

File utama:

- `app/Http/Middleware/TenantResolver.php`

Temuan:

- API request wajib punya tenant context yang valid.
- Jika tenant pada request tidak cocok dengan `tenant_id` user, middleware mengembalikan `TENANT_MISMATCH`.
- Jika tenant user tidak ditemukan, request gagal di level middleware.

Risiko:

- Ini dapat memunculkan 400/403 baru pada client yang sebelumnya lolos.
- Integration test dan tooling yang tidak konsisten mengirim tenant header bisa gagal mendadak.

### 2. Role Resolution Sekarang Bergantung pada Normalization

File utama:

- `app/Http/Middleware/RoleMiddleware.php`

Temuan:

- Semua pengecekan role sekarang bergantung pada `UserRoleResolverService`.
- Fallback ke `user->role` masih ada, tetapi tetap dinormalisasi.

Risiko:

- Mapping role yang tidak lengkap bisa mengubah akses banyak endpoint sekaligus.
- Error sekarang lebih deterministik, tetapi juga lebih ketat.

### 3. Web Access Menjadi Gate Portal + Role + Feature

File utama:

- `app/Http/Middleware/WebAccessMiddleware.php`

Temuan:

- Middleware ini menangani autentikasi web, portal aktif, allowed roles, impersonation superadmin, dan feature access sekaligus.
- Jika portal tidak cocok, user akan diarahkan ulang.
- Jika role tidak cocok, akses dihentikan dengan 403.
- Jika feature tidak diizinkan oleh subscription, akses juga dihentikan.

Risiko:

- Ini adalah middleware dengan tanggung jawab besar dan impact luas.
- Kombinasi redirect + role + feature gate sangat rawan memicu regression yang sulit dilacak bila diuji terpisah-pisah.

### 4. Feature Gate dan Subscription Status Sekarang Menentukan Akses Route

File utama:

- `app/Http/Middleware/FeatureGateMiddleware.php`
- `app/Http/Middleware/SubscriptionStatusMiddleware.php`

Temuan:

- Route kini bisa gagal hanya karena paket atau status subscription tidak sesuai.
- Detail error sudah eksplisit, tetapi behavior aplikasi berubah dari role-based menjadi plan-aware.

Risiko:

- Endpoint yang dulu pass di lingkungan dev bisa menjadi 403 jika subscription state tidak disiapkan.
- Butuh seed/test fixture subscription yang lebih disiplin.

### 5. Security Headers Berlaku Global

File utama:

- `app/Http/Middleware/SecurityHeadersMiddleware.php`

Temuan:

- Security headers dan CSP diterapkan global.
- Middleware mencoba menambahkan nonce ke script pada HTML response.

Risiko:

- Asset atau inline script yang belum sesuai CSP bisa rusak di UI.
- Ini harus divalidasi lewat smoke test web, bukan hanya API test.

## Risiko Terbesar

1. 400/403 baru dari tenant mismatch dan feature gating.
2. Redirect web yang tidak stabil karena portal/access middleware.
3. UI pecah karena CSP dan security headers global.
4. Test fixture lama tidak cukup karena sekarang harus menyiapkan subscription dan role resolution yang benar.

## Rekomendasi

1. Review middleware residual ini bersama batch route web dan API, bukan terpisah jauh.
2. Tambahkan smoke test khusus untuk login portal, akses halaman backoffice/POS, dan gating valuation/subscription.
3. Jangan gabungkan middleware residual ini ke PR audit final observability.