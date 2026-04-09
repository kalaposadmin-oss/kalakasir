# PR Summary - Production Schema and Waste Fields 2026-04-09

## Ringkasan

Batch ini menambahkan schema pendukung flow onboarding/production yang sudah lebih dulu masuk di layer service dan view, sekaligus melengkapi tagging waste pada stock adjustment.

## Cakupan

- migrasi `business_category` pada tenant untuk onboarding/profile bisnis
- ekspansi presisi kuantitas ke scale 4 pada kolom inventory/production yang relevan
- migrasi `production_mode` pada product beserta backfill produk BOM lama ke `auto_consume_on_sell`
- perluasan `production_batches` dan `production_batch_items` untuk planned vs actual yield, referensi branch/product, metadata variance, dan cancel reason
- field `waste_type` dan `production_batch_id` pada `stock_adjustments`
- test tenant scoping production batch dan validasi waste-tagging stock adjustment

## Nilai Perubahan

- flow produksi dan onboarding yang sudah ada sekarang punya schema yang sesuai dengan kontrak runtime
- quantity precision konsisten untuk kebutuhan yield, recipe, dan inventory yang butuh 4 digit desimal
- stock adjustment bisa menandai waste dan mengaitkannya ke production batch secara tenant-safe

## Validasi

- `tests: all` -> `560 passed (3375 assertions)`