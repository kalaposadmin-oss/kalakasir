# PR Summary Product Item Type Foundation 2026-04-09

## Scope

PR ini hanya membawa fondasi product item type untuk mode produk dan backfill checkpoint. Controller katalog, material API, variant lifecycle, dan flow POS yang lebih besar tetap di luar scope.

## Included Files

- `app/Jobs/BackfillProductItemTypeJob.php`
- `app/Services/ProductItemTypeMapper.php`
- `app/Services/ProductItemTypeValidator.php`
- `database/migrations/2026_03_03_000001_add_product_mode_fields_to_products_table.php`
- `database/migrations/2026_03_13_100000_add_item_type_to_products_table.php`
- `database/migrations/2026_03_13_100100_create_product_item_type_backfill_checkpoints_table.php`
- `tests/Feature/ProductItemTypeBackfillGateTest.php`
- `tests/Feature/ProductItemTypeSupportTest.php`

## What Changed

- menambahkan field dasar `product_mode`, `variants`, `bom_items`, dan `bundle_items` pada `products`
- menambahkan field `item_type` dan index tenant/item_type pada `products`
- menambahkan tabel checkpoint untuk backfill bertahap `product_item_type`
- menambahkan job `BackfillProductItemTypeJob` dengan checkpoint cursor dan cache lock per tenant scope
- menambahkan helper mapping dan validation untuk kombinasi `product_mode`, `item_type`, `track_stock`, dan production mode

## Validation

- `php artisan test tests/Feature/ProductItemTypeBackfillGateTest.php tests/Feature/ProductItemTypeSupportTest.php` passed
- focused run mencakup command audit indicator, checkpoint creation, batch backfill mapping, migration rollback/reapply, mapper fallback, dan validator guard

## Explicitly Excluded

- `ProductController`
- `MaterialController`
- `ProductItemTypeCatalogApiTest.php`
- variant/register/discount/loyalty migrations
- material model/API dan POS checkout/catalog flows