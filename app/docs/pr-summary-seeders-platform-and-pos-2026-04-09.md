# PR Summary - Platform and POS Seeders 2026-04-09

## Ringkasan

Batch ini menambahkan seeder operasional untuk akun superadmin platform dan data master POS demo agar lingkungan lokal/QA punya baseline data yang konsisten.

## Cakupan

- `PlatformSuperadminSeeder` untuk memastikan akun dan role `superadmin` platform tersedia
- `PosMasterSeeder` untuk melengkapi register, payment method, variant, pricing rule, voucher, cash flow, dan data POS pendukung tenant pertama

## Nilai Perubahan

- reviewer dan QA bisa menyalakan surface platform tanpa setup manual berulang
- data demo POS lebih lengkap untuk validasi flow kasir, pembayaran, pricing, dan voucher

## Validasi

- `php artisan db:seed --class=PlatformSuperadminSeeder`
- `php artisan db:seed --class=PosMasterSeeder`