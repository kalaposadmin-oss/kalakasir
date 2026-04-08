# Commit Message Refund Core API 2026-04-08

```text
feat(api): add core refund endpoints with idempotent create flow

Add the core refund API surface for listing, detail, and create flows.

- add tenant-safe refund list/detail endpoints
- add idempotent refund create flow keyed by sale_item_id
- persist refund payment allocation and financial snapshots
- reverse stock, valuation allocation, cash flow, and GL entries in one transaction

Validated with focused refund contract, detail, tenant, service, and variant lifecycle tests.
```