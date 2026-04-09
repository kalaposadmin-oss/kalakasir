# Review Web Routes Findings 2026-04-08

## Findings

### 1. Portal-specific login pages bypass access validation for already authenticated users

Severity: tinggi

Lokasi:

- `app/Http/Controllers/Web/PortalAuthController.php`
- `routes/web.php`

Rincian:

- `showPos()` dan `showBackoffice()` langsung menaruh `auth_portal` ke session lalu redirect ke dashboard portal masing-masing jika `Auth::check()` bernilai true.
- Berbeda dengan `attemptLogin()`, dua method ini tidak memanggil `portalAccessError()` sebelum redirect.

Risiko:

- User yang sudah login tetapi tidak berhak ke portal tertentu bisa diarahkan dulu ke dashboard portal itu.
- Pada praktiknya ini mungkin kemudian tertahan oleh `web_access`, tetapi flow menjadi tidak bersih dan berpotensi memunculkan redirect/403 yang membingungkan.

### 2. Root guest flow becomes a two-step redirect by framework configuration

Severity: sedang

Lokasi:

- `bootstrap/app.php`
- `routes/web.php`

Rincian:

- `redirectGuestsTo()` mengarahkan web guest ke `/`.
- Route `/` lalu mengarahkan guest normal ke `/login` atau ke onboarding jika tenant belum punya user.

Risiko:

- Ada hop redirect tambahan untuk semua guest web request.
- Tidak fatal, tetapi menambah kompleksitas saat men-debug login, middleware auth, dan intended redirect.

### 3. Legacy redirect layer is now large enough to require explicit smoke coverage

Severity: tinggi

Lokasi:

- `routes/web.php`

Rincian:

- Banyak route lama sekarang hanya menjadi redirect wrapper ke `/pos/*` atau `/backoffice/*`.
- Beberapa redirect menyusun ulang query string dan path entity secara manual.

Risiko:

- Satu bug kecil di mapping query parameter bisa membuat detail page, stock card, receiving action, atau sales detail mendarat ke halaman yang salah.
- Ini area yang tidak otomatis terlindungi oleh test API.

### 4. Onboarding flow now owns business provisioning responsibilities

Severity: tinggi

Lokasi:

- `routes/web.php`

Rincian:

- `POST /register/step-2` sekarang membuat atau memperbarui tenant, membuat owner, assign role owner, memastikan branch/location default, dan memastikan accounting default.
- `POST /register/step-4` sekarang bisa membuat product, variants, initial stock, stock ledger, dan valuation receive.

Risiko:

- Logic provisioning besar berada langsung di route closure.
- Regression di sini bukan sekadar UI onboarding gagal, tetapi bisa menghasilkan tenant setup yang setengah jadi atau duplicate provisioning.

### 5. Portal routing depends tightly on host configuration

Severity: sedang

Lokasi:

- `app/Support/WebPortalResolver.php`
- `routes/web.php`

Rincian:

- Portal aktif dan canonical redirect bergantung pada konfigurasi host portal.
- Jika host canonical kosong, resolver fallback ke path lokal.
- Jika host ada, canonical redirect ikut memakai scheme dan port dari request.

Risiko:

- Konfigurasi host yang tidak lengkap atau salah di staging/production bisa mengubah perilaku redirect lintas portal.
- Ini perlu divalidasi per environment, bukan hanya di local test.

## Minimum Smoke Checklist

1. Guest membuka `/`, `/login`, `/pos/login`, dan `/backoffice/login`.
2. User owner login dari generic login lalu mendarat ke portal yang benar.
3. User tanpa akses backoffice mencoba `/backoffice/login` saat sudah login.
4. Legacy paths seperti `/sales-history`, `/inventory`, dan `/kasir` tetap membawa query string dan mendarat ke target benar.
5. Onboarding penuh tenant baru sampai auto-login ke `/pos/dashboard`.