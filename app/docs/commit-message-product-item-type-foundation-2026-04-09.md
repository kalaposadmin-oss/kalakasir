# Commit Message Product Item Type Foundation 2026-04-09

```text
feat(app): add product item type backfill foundation

Add the foundational product_mode and item_type support needed for staged
catalog cleanup and backfill.

- add product_mode and item_type schema support with backfill checkpoints
- add product item type mapper and validator helpers
- add checkpointed backfill job for staged product item type migration
- include focused tests for backfill, migration safety, mapper, and validator rules

Validated with focused product item type feature tests.
```