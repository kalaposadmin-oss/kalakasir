# Review Web Routes Residual 2026-04-08

## Kesimpulan

`routes/web.php` adalah residual batch terbesar dan paling berisiko di worktree saat ini. File ini bukan sekadar penambahan route, tetapi rewrite alur portal web, onboarding, login, redirect legacy, dan akses POS/backoffice.

## Perubahan Utama

### 1. Root dan Login Flow Berubah

Temuan utama:

- Root sekarang mengarah ke login, bukan dashboard lama.
- Generic login diperkenalkan pada `/login`.
- Login spesifik portal diperkenalkan pada `/pos/login` dan `/backoffice/login`.
- Ada endpoint portal switch di `/portal/switch/{portal}`.

Implikasi:

- Semua flow masuk aplikasi berubah.
- Risiko regression tertinggi ada pada redirect loop, salah portal landing, dan bookmark lama.

### 2. Onboarding Menjadi Flow Provisioning Penuh

Temuan utama:

- `POST /register/step-2` tidak lagi sekadar menyimpan profil toko, tetapi juga bisa membuat tenant baru atau memperbarui tenant yang ada.
- Flow ini sekarang memanggil `TenantDefaultRoleSetupService` untuk assign Owner.
- Flow ini juga memanggil `TenantDefaultAccountingSetupService`.
- `POST /register/step-4` menulis produk awal, varian, saldo stok awal, stock ledger, dan memanggil `InventoryValuationService`.
- Setelah onboarding selesai, user di-login otomatis dan diarahkan ke `/pos/dashboard`.

Implikasi:

- `routes/web.php` sekarang mengandung business logic onboarding yang besar.
- Regression di sini bisa berdampak ke pembuatan tenant, role owner, stok awal, dan costing awal.

### 3. Legacy Routes Menjadi Redirect Layer

Temuan utama:

- Route lama seperti `/inventory`, `/sales-history`, `/kasir`, dan `/settings/chart-of-accounts` sekarang redirect ke route portal baru.
- Ini mempermudah migrasi UI, tetapi memperbesar permukaan redirect yang harus diuji manual.

Implikasi:

- Harus ada validasi bahwa query string lama tetap terjaga.
- Harus diuji apakah redirect sesuai portal aktif user.

### 4. Portal Separation Aktif di Routing

Temuan utama:

- Ada group route portal POS dengan `canonical_portal_host:pos` dan `web_access:pos,...`.
- Ada group route backoffice dengan `canonical_portal_host:backoffice` dan `web_access:backoffice,...`.
- Ini berarti `web.php` sekarang tergantung erat pada middleware baru dan portal resolver.

Implikasi:

- Perubahan ini harus direview dan dites bersama middleware, tidak bisa dipisah setengah.
- Salah konfigurasi middleware alias atau host canonicalization bisa memutus akses seluruh portal.

## Risiko Terbesar

1. Redirect loop antara login, root, portal switch, dan route legacy.
2. Salah portal landing untuk role tertentu.
3. Onboarding menghasilkan tenant/setup awal yang tidak lengkap atau ganda.
4. Route lama masih terbuka tetapi mengarah ke target yang salah.
5. Ketergantungan kuat pada middleware baru yang belum menjadi bagian dari batch audit final.

## Rekomendasi

1. Perlakukan `routes/web.php` sebagai batch terpisah dari audit final.
2. Review dan test bersama middleware portal/access terkait.
3. Siapkan manual QA khusus untuk login, onboarding, route legacy redirect, dan portal switching.
4. Jangan campurkan batch ini dengan PR observability audit yang sempit.