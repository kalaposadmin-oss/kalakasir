# CI Activation Checklist

Dokumen ini untuk mengaktifkan test matrix CI setelah repository di-push ke GitHub.

## Prasyarat
- Repository sudah punya remote `origin` ke GitHub.
- GitHub Actions di repository tidak di-disable.

## Langkah Aktivasi
1. Pastikan workflow tersedia di `.github/workflows/ci-tests.yml`.
2. Push branch `main` ke remote GitHub.
3. Buka tab **Actions** dan jalankan workflow **CI Tests** sekali secara manual (opsional), atau trigger via push.
4. Verifikasi semua lane hijau:
   - Unit Tests
   - Valuation Tests
   - Write Flow Tests

## Troubleshooting singkat
- Jika `composer install` gagal, cek versi PHP (target `8.3`) dan extension `pdo_sqlite`.
- Jika lane fail karena env, cek step `Prepare environment` pada workflow.
- Jika workflow tidak berjalan pada PR, cek setting permission Actions di repository.
