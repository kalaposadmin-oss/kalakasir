# Commit Message Subscription Core Invoices 2026-04-09

```text
feat(api): add subscription core and invoice endpoints

Add the tenant-facing subscription and billing API surface.

- add current plan, entitlements, impact preview, and audit log endpoints
- add idempotent subscription plan change flow with scheduled downgrade support
- add invoice list, detail, download, and mark-paid endpoints with tenant scoping
- add subscription audit/billing schema and feature gate config source of truth

Validated with focused subscription plan, core API, and invoice scoping tests.
```