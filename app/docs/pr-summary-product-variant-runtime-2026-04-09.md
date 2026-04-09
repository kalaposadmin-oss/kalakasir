# PR Summary Product Variant Runtime 2026-04-09

## Scope

PR ini hanya membawa runtime variant-aware untuk create, update, dan show product. Scope-nya mencakup payload variants, initial stock per variant, sinkronisasi variant aktif, dan payload detail product yang sudah memuat variant aktif. Checkout, void, dan valuation replay variant tetap di luar scope.

## Included Files

- `app/Http/Controllers/Api/ProductController.php`
- `app/Models/Product.php`
- `tests/Feature/ProductItemTypeCatalogApiTest.php`

## What Changed

- menambahkan dukungan payload `variants` dan `initial_stock_lines` pada create/update product
- menambahkan validasi variant stock lines, duplikasi variant, dan kecocokan variant terhadap product
- menyinkronkan row `product_variants` aktif saat product dibuat atau diperbarui
- mem-post initial stock ke bucket inventory dan valuation per variant
- menambahkan payload `product_variants` dan `has_variants` pada endpoint show product

## Validation

- `php artisan test tests/Feature/ProductItemTypeCatalogApiTest.php` passed

## Explicitly Excluded

- checkout dan void runtime variant
- purchase, transfer, dan adjustment variant runtime
- valuation replay dan reversal variant flow