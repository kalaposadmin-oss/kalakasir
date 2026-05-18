# Deployment Hosting KalaKasir

Panduan ini untuk menyiapkan repo agar rapi di GitHub dan mudah dinaikkan ke hosting. Aplikasi Laravel utama ada di folder `app/`.

## Checklist Sebelum Push ke GitHub

1. Pastikan file rahasia tidak ikut commit:
   - jangan commit `app/.env`
   - jangan commit credential database, SMTP, token, atau key produksi
   - pakai `app/.env.example` untuk lokal dan `app/.env.hosting.example` untuk template hosting
2. Pastikan file generated tidak ikut commit:
   - `app/vendor/`
   - `app/node_modules/`
   - `app/public/build/`
   - `app/storage/**` selain `.gitignore`
   - file `tmp-*`, session, log, backup lokal
3. Jalankan validasi dari folder `app/`:
   ```bash
   composer install
   npm install
   php artisan test
   npm run build
   ```
4. Commit hanya source code, config contoh, migration, view, test, docs, dan lockfile.
5. Jalankan cek cepat dari root repo:
   ```bash
   git status --short
   git check-ignore -v app/.env app/vendor app/node_modules app/public/build
   ```

File `composer.json` dan `package.json` yang aktif ada di `app/`, bukan di root repo. Ini penting supaya GitHub Actions dan hosting menjalankan dependency dari folder Laravel yang benar.

## Struktur Repo

```txt
kalakasir/
+-- app/          # Laravel app utama
+-- docs/         # Dokumentasi operasional/deploy
+-- mobile/       # Source mobile bila dipakai
+-- .github/      # CI dan deploy workflow
+-- README.md
```

## Setup Hosting Pertama Kali

Masuk ke folder aplikasi Laravel:

```bash
cd app
composer install --no-dev --optimize-autoloader
npm ci --include=dev
npm run build
cp .env.hosting.example .env
php artisan key:generate
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
```

Edit `app/.env` di hosting sesuai database, domain, SMTP, dan portal host.
Set `SECURITY_MANAGER_PIN` atau `ORDER_ONLINE_ADMIN_PIN` dengan PIN produksi yang kuat, jangan memakai nilai demo.

## Document Root Hosting

Rekomendasi terbaik:

```txt
Document root -> /path/kalakasir/app/public
```

Jangan arahkan document root ke folder repo root atau folder `app/`, karena file Laravel internal harus tetap di luar akses publik.

Jika shared hosting/cPanel tidak bisa mengarah ke `app/public`, opsi aman:

1. Simpan folder Laravel di luar `public_html`, misalnya `/home/user/kalakasir/app`.
2. Isi `public_html` hanya dari isi folder `app/public`.
3. Sesuaikan path di `public_html/index.php` agar menunjuk ke:
   - `../kalakasir/app/vendor/autoload.php`
   - `../kalakasir/app/bootstrap/app.php`

## Deploy Update Berikutnya

Untuk VPS atau hosting yang bisa `git pull`:

```bash
cd /path/kalakasir
git pull origin main
cd app
composer install --no-dev --optimize-autoloader
npm ci --include=dev
npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
php artisan optimize
```

## Queue dan Scheduler

Jika `QUEUE_CONNECTION=database`, jalankan worker:

```bash
php artisan queue:work --tries=3 --timeout=90
```

Tambahkan scheduler di cron hosting:

```bash
* * * * * cd /path/kalakasir/app && php artisan schedule:run >> /dev/null 2>&1
```

## GitHub Actions

Workflow CI ada di `.github/workflows/ci-tests.yml` dan akan mengecek build frontend serta test Laravel.

Workflow deploy staging ada di `.github/workflows/deploy-staging.yml`. Secret yang perlu disiapkan di GitHub:

- `DEPLOY_HOST`
- `DEPLOY_USER`
- `DEPLOY_PORT`
- `DEPLOY_SSH_KEY`
- `STAGING_BASE_URL` opsional, contoh `https://staging.example.com`
- `STAGING_HEALTHCHECK_URL` opsional, contoh `https://staging.example.com/up`

Pastikan `DEPLOY_PATH` di workflow mengarah ke root repo di server, misalnya `/var/www/kalakasir`, karena command Laravel dijalankan dari `app/`.

## Shared Hosting Tanpa SSH

Jika hosting tidak menyediakan SSH, siapkan build dari komputer lokal:

```bash
cd app
composer install --no-dev --optimize-autoloader
npm ci --include=dev
npm run build
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

Upload isi repo tanpa file yang di-ignore oleh Git. Setelah upload, buat `app/.env` dari `app/.env.hosting.example`, isi konfigurasi produksi, lalu arahkan document root ke `app/public`.

## Akun Superadmin Lokal

Untuk development lokal, seed superadmin dapat dibuat dengan:

```bash
php artisan db:seed --class=PlatformSuperadminSeeder
```

Gunakan akun demo hanya untuk lokal. Untuk produksi, ganti password setelah deploy.
