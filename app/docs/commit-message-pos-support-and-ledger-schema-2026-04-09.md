# Commit Message POS Support and Ledger Schema 2026-04-09

## Subject

`feat(app): add pos support and ledger hardening schema`

## Body

```text
Add the remaining POS support tables and stock ledger hardening fields used by
the newer pricing, voucher, cash flow, and opening stock flows.

- add ledger cost and opening key fields
- add POS support tables for pricing, vouchers, tax lines, cash flows, loyalty, notifications, and settings
- keep schema tenant-scoped with foreign key constraints

Validation:
- tests: all
```