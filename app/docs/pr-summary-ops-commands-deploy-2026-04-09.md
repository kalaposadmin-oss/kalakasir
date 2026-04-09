# PR Summary Ops Commands Deploy 2026-04-09

## Scope

PR ini hanya membawa command operasional tambahan di `routes/console.php` dan workflow deploy staging. Middleware, platform admin API, dan feature controller batch lain tetap di luar scope.

## Included Files

- `routes/console.php`
- `.github/workflows/deploy-staging.yml`
- dokumen handoff PR/commit untuk slice operasional ini

## What Changed

### Console Commands

- menambahkan `pos:audit-consistency` untuk audit indikator migrasi katalog POS berdasarkan `item_type` dan `product_mode`
- menambahkan `valuation:backfill-purchase-layer-source-items` untuk audit dan optional backfill `source_item_id` history purchase layer tanpa menebak row ambigu

### Deploy Workflow

- menambahkan workflow manual `Deploy Staging`
- workflow mendukung `dry_run`, deploy via SSH, `composer install`, optional `npm ci && npm run build`, cache warmup, migration, queue restart, healthcheck, smoke HEAD request, dan rollback ke commit sebelumnya jika gagal

## Validation

- `php artisan list --raw` passed dan menampilkan `pos:audit-consistency` serta `valuation:backfill-purchase-layer-source-items`
- `php artisan schedule:list` passed
- workflow YAML direview manual sebagai workflow dispatch staging dengan rollback trap dan healthcheck

## Explicitly Excluded

PR ini sengaja tidak membawa:

- command classes existing yang tidak berubah di worktree
- middleware files
- API/web controller batches yang sudah di-commit sebelumnya