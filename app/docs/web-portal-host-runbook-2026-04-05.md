# Web Portal Host Runbook

## Tujuan

Menjalankan satu instance web Laravel dengan dua entry host:
- `pos.127.0.0.1.nip.io:8000` untuk shell POS
- `admin.127.0.0.1.nip.io:8000` untuk shell Backoffice

Role tetap menentukan izin. Host menentukan portal default dan tampilan login pada generic `/login`.

## Konfigurasi `.env`

Pastikan nilai berikut terisi:

```env
APP_URL=http://127.0.0.1:8000
POS_PORTAL_HOSTS=pos.127.0.0.1.nip.io
BACKOFFICE_PORTAL_HOSTS=admin.127.0.0.1.nip.io
SESSION_COOKIE=kalakasir-portal-session
SESSION_DOMAIN=.127.0.0.1.nip.io
SANCTUM_STATEFUL_DOMAINS=127.0.0.1:8000,localhost:8000,pos.127.0.0.1.nip.io:8000,admin.127.0.0.1.nip.io:8000
```

`SESSION_DOMAIN` penting agar login yang dibuat di host POS tetap terbaca saat switch ke host Backoffice, dan sebaliknya.

`SESSION_COOKIE` sengaja dibedakan dari nama lama untuk menghindari bentrok dengan cookie host-only lama yang masih tersimpan di browser.

## Menjalankan Lokal

1. Buka terminal di folder `app`.
2. Jalankan `php artisan serve --host=127.0.0.1 --port=8000`.
3. Buka `http://pos.127.0.0.1.nip.io:8000/login`.
4. Buka `http://admin.127.0.0.1.nip.io:8000/login`.
5. Jika sebelumnya sudah login sebelum `SESSION_DOMAIN` dibenahi, logout lalu login ulang sekali agar browser menerima cookie baru.

Wildcard `nip.io` dipilih agar tidak perlu edit file hosts Windows untuk verifikasi lokal.

## Manual Check

1. `http://pos.127.0.0.1.nip.io:8000/login` menampilkan copy `Login POS`.
2. `http://admin.127.0.0.1.nip.io:8000/login` menampilkan copy `Login Backoffice`.
3. Owner login dari host POS diarahkan ke `/pos/dashboard`.
4. Owner login dari host Backoffice diarahkan ke `/backoffice/dashboard`.
5. Cashier login dari kedua host tidak mendapat akses backoffice.
6. Owner di layar POS utama dan layar transaksi tetap berada di shell POS, tetapi melihat shortcut manajemen untuk produk, stok, laporan, dan pengaturan.
7. Cashier di layar POS tidak melihat shortcut manajemen tersebut.

## Opsi Domain Kustom

Jika perlu memakai domain seperti `pos.kalakasir.test` dan `office.kalakasir.test`, tambahkan mapping loopback di file hosts OS lalu sesuaikan:

```env
POS_PORTAL_HOSTS=pos.kalakasir.test
BACKOFFICE_PORTAL_HOSTS=office.kalakasir.test
```

## Guardrail

- Generic `/login` memakai host request lebih dulu.
- Route eksplisit `/pos/login` dan `/backoffice/login` tetap tersedia sebagai fallback operasional.
- Regression test yang relevan:
  - `tests/Feature/WebAuthOnboardingSmokeTest.php`
  - `tests/Feature/PortalWebAccessTest.php`

## Catatan Windows Lokal

Jika `artisan serve` masih menampilkan copy portal utama pada hostname alternatif, pastikan host disimpan di file `.env`, bukan hanya di env shell sementara.

Jika perubahan `.env` tidak terbaca, cek dan hapus env shell yang masih menimpa proses saat ini, terutama `POS_PORTAL_HOSTS` dan `BACKOFFICE_PORTAL_HOSTS`.

Jika gejalanya adalah "sudah login di POS tapi saat buka tambah produk/backoffice diminta login lagi", hampir selalu penyebabnya `SESSION_DOMAIN` belum diisi ke domain induk bersama, misalnya `.127.0.0.1.nip.io`.

Helper lokal `scripts/dev/ensure_portal_qa_users.php` dipertahankan sebagai util QA/dev untuk menyiapkan akun owner dan cashier yang konsisten saat verifikasi manual host portal.