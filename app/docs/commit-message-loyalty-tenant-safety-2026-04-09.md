# Commit Message Loyalty Tenant Safety 2026-04-09

```text
feat(app): harden loyalty tenant scoping and actions

Tighten the loyalty API and loyalty backoffice page so they respect tenant
boundaries and only expose management actions to elevated roles.

- scope loyalty tiers, customers, and sales to the active tenant
- hide loyalty management actions from non-admin backoffice roles
- align the loyalty page with the current backoffice shell
- include focused tenant-scoping coverage for the loyalty endpoint

Validated with focused loyalty, smoke, and portal access tests.
```