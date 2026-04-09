# PR Summary - QA Portal Helper Script 2026-04-09

## Ringkasan

Batch ini menambahkan script helper lokal untuk memastikan akun QA portal tersedia tanpa harus membuat user manual lewat UI atau tinker.

## Cakupan

- `scripts/dev/ensure_portal_qa_users.php` yang bootstrap Laravel, mengambil tenant pertama, lalu memastikan user QA owner dan cashier tersedia dengan role yang benar

## Nilai Perubahan

- manual QA untuk portal login dan role separation jadi lebih cepat dan repeatable
- role assignment menggunakan service setup yang sama dengan jalur aplikasi

## Validasi

- `php scripts/dev/ensure_portal_qa_users.php`