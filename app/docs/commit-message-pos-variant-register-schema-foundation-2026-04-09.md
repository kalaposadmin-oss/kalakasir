# Commit Message POS Variant Register Schema Foundation 2026-04-09

```text
feat(app): add pos register and variant schema foundation

Add the schema layer required for POS registers, payment methods, and
variant-aware inventory buckets.

- add registers, product_variants, and payment_methods tables
- add register_id and payment_method_id references for transaction flows
- add product_variant_id across transactional, inventory, and valuation tables
- add supporting POS and variant indexes plus null-safe inventory bucket uniqueness

Validated with migration syntax checks and the existing variant lifecycle test in the current worktree.
```