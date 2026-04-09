# PR Ready Product Variant Runtime 2026-04-09

## PR Title

Add product variant create update and show runtime

## Commit Message

```text
feat(app): add product variant runtime for catalog APIs

Extend the product catalog APIs so products can define active variants,
post initial stock per variant, and expose variant data on product detail.

- accept variants and initial_stock_lines on product create and update
- sync active product_variants rows from catalog payloads
- post initial stock into variant-aware inventory and valuation buckets
- expose active variants and has_variants on product detail

Validated with focused product catalog API tests.
```

## PR Description

```markdown
## Scope

This PR isolates runtime product variant handling for catalog APIs. It keeps checkout, void, purchase, and valuation replay variant flows out of scope.

## Included Files

- app/Http/Controllers/Api/ProductController.php
- app/Models/Product.php
- tests/Feature/ProductItemTypeCatalogApiTest.php

## What Changed

- accept variants and initial stock lines in product create and update payloads
- validate variant payload consistency and per-variant stock initialization
- sync active product variants when catalog records change
- expose active variants and has_variants in product detail responses

## Validation

- php artisan test tests/Feature/ProductItemTypeCatalogApiTest.php passed

## Explicitly Excluded

- checkout and void variant runtime
- purchase and stock movement variant runtime
- valuation replay and reversal flows
```