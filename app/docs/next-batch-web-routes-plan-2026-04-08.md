# Next Batch Web Routes Plan 2026-04-08

## Tujuan

Menangani residual batch besar di `routes/web.php` sebagai pekerjaan terpisah setelah batch audit final dipisahkan.

## Scope Batch Berikutnya

1. Root, login, dan portal-specific login.
2. Portal switching dan canonical host flow.
3. Onboarding step 2 sampai step 4.
4. Legacy route redirects ke `/pos/*` dan `/backoffice/*`.
5. Group route POS dan backoffice yang tergantung ke `web_access` dan `canonical_portal_host`.

## Urutan Review

### 1. Auth Entry Flow

Periksa:

- `/`
- `/login`
- `/pos/login`
- `/backoffice/login`
- `/logout`

Tujuan:

- pastikan tidak ada redirect loop
- pastikan landing page sesuai portal

### 2. Portal Switch dan Active Portal State

Periksa:

- `/portal/switch/{portal}`
- session key `auth_portal`
- resolver portal aktif

Tujuan:

- pastikan transisi POS <-> backoffice konsisten
- pastikan role tanpa akses tidak masuk portal yang salah

### 3. Onboarding Provisioning Flow

Periksa:

- `POST /register/step-2`
- `POST /register/step-4`
- tenant creation/update
- owner role assignment
- default branch/location setup
- accounting setup
- initial stock and valuation receive flow

Tujuan:

- pastikan tidak ada double provisioning
- pastikan tenant setup awal lengkap

### 4. Legacy Redirect Map

Periksa redirect lama seperti:

- `/inventory`
- `/sales-history`
- `/kasir`
- `/settings/chart-of-accounts`

Tujuan:

- pastikan query string tetap terbawa
- pastikan target route sesuai portal aktif

### 5. POS and Backoffice Route Groups

Periksa:

- group POS dengan `canonical_portal_host:pos`
- group backoffice dengan `canonical_portal_host:backoffice`
- penggunaan `web_access`

Tujuan:

- pastikan wiring route dan middleware konsisten
- tandai halaman yang butuh smoke test manual

## Output yang Diharapkan

1. Ringkasan file/flow mana yang aman dipisah menjadi PR tersendiri.
2. Daftar risiko regression web paling tinggi.
3. Daftar smoke test manual minimum untuk login, onboarding, redirect legacy, dan portal switch.