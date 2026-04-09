# PR Ready - Production Schema and Waste Fields 2026-04-09

## Judul PR

`feat(app): add production schema and waste adjustment fields`

## Fokus Reviewer

- pastikan migrasi precision aman pada engine database yang didukung dan tidak mengubah semantik field non-kuantitas
- pastikan backfill `production_mode` hanya menyentuh produk BOM legacy yang memang harus menjadi `auto_consume_on_sell`
- pastikan kolom baru production batch cukup untuk flow actual yield, variance, dan cancel reason yang sudah dipakai runtime
- pastikan referensi `production_batch_id` dan field waste di stock adjustment tetap tenant-scoped lewat validasi feature test

## Validasi

- `tests: all`