# Commit Message Refund Legacy Audit 2026-04-09

```text
feat(api): add refund legacy audit and remediation surface

Add the refund legacy audit API and operational remediation commands.

- add audit, history, export, and remediation endpoints for legacy refund rows
- add console commands to inspect, export, and backfill historical refund_items
- persist remediation audit logs and CSV export support
- harden remediation matching to respect product_variant_id when present

Validated with focused refund legacy audit API, command, and web smoke tests.
```