# Audit Final Batch 2026-04-08

Dokumen ini memisahkan batch penutupan audit terakhir dari perubahan worktree lain yang tidak terkait langsung.

## Masih Berbeda di Worktree

File berikut masih muncul di `git diff` dan merupakan kandidat batch final yang perlu dipertahankan sebagai satu paket:

- `app/Http/Controllers/Api/BootstrapController.php`
- `app/Http/Controllers/Api/CheckoutController.php`
- `app/Http/Controllers/Api/FinanceControlDashboardController.php`

Ringkasan diff terfokus untuk set ini:

- `3 files changed`

Catatan:

- `tests/Feature/FinanceControlDashboardTest.php` termasuk cakupan batch ini, tetapi pada verifikasi terakhir tidak lagi muncul sebagai diff aktif terhadap HEAD.

## Set Siap PR / Cherry-pick

Jika ingin mengambil batch audit final yang masih aktif di worktree tanpa ikut membawa perubahan unrelated lain, prioritaskan set berikut:

- `app/Http/Controllers/Api/BootstrapController.php`
- `app/Http/Controllers/Api/CheckoutController.php`
- `app/Http/Controllers/Api/FinanceControlDashboardController.php`

Jika ingin paket audit akhir dibawa lengkap sebagai catatan handoff, tambahkan file terkait yang sudah bersih vs HEAD berikut sebagai referensi validasi:

- `database/migrations/2026_04_08_120000_expand_quantity_precision_to_scale_four.php`
- `tests/Feature/BootstrapTenantScopingTest.php`
- `tests/Feature/WriteFlowTransactionDateHappyPathTest.php`
- `tests/Feature/VoidServiceInventoryReversalTest.php`

## Contoh Ambil Batch Ini Saja

Untuk branch atau worktree lain, ambil file yang masih aktif di diff sekarang:

```powershell
git checkout <source-branch> -- \
	app/app/Http/Controllers/Api/BootstrapController.php \
	app/app/Http/Controllers/Api/CheckoutController.php \
	app/app/Http/Controllers/Api/FinanceControlDashboardController.php
```

Atau kalau ingin commit parsial dari worktree saat ini, stage hanya file batch final:

```powershell
git add \
	app/app/Http/Controllers/Api/BootstrapController.php \
	app/app/Http/Controllers/Api/CheckoutController.php \
	app/app/Http/Controllers/Api/FinanceControlDashboardController.php
```

Lalu verifikasi ulang dengan:

```powershell
git diff --cached --stat
php artisan test
```

## Terkait Batch Ini, Tetapi Sudah Bersih vs HEAD

File berikut ada dalam cakupan audit akhir dan sudah tervalidasi, tetapi saat pengecekan terakhir tidak lagi muncul di `git diff`:

- `database/migrations/2026_04_08_120000_expand_quantity_precision_to_scale_four.php`
- `tests/Feature/BootstrapTenantScopingTest.php`
- `tests/Feature/FinanceControlDashboardTest.php`
- `tests/Feature/WriteFlowTransactionDateHappyPathTest.php`
- `tests/Feature/VoidServiceInventoryReversalTest.php`

Ini biasanya berarti file tersebut sudah sama dengan basis repo saat ini, atau perubahannya sudah terserap oleh perubahan lain sebelumnya.

## Cakupan Perubahan Batch Final

- Header observability untuk bootstrap, checkout, dan finance control.
- Guard read-only dan timing response pada finance control.
- Coverage test untuk observability dan jalur write-flow/void replay yang relevan.
- Migration precision quantity skala 4 yang sudah dijalankan sukses.

## Status Verifikasi

- `php artisan migrate`: sukses.
- `php artisan test`: sukses.
- Full suite sebelumnya lulus `491` test.

## Catatan Worktree

Worktree saat ini masih memuat banyak perubahan besar lain di routing, middleware, subscription/platform, view, dan area APK/web. Perubahan tersebut tidak otomatis termasuk batch audit final ini dan sebaiknya direview terpisah.