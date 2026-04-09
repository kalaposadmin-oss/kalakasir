# Commit Message Product Variant Runtime 2026-04-09

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