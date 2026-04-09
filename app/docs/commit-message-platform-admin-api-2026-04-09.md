# Commit Message Platform Admin API 2026-04-09

```text
feat(api): add platform admin endpoints

Add the platform admin API surface for superadmin dashboards, cross-tenant support,
and controlled danger actions.

- add platform dashboard, tenant, user, invoice, payment, anomaly, support, and settings endpoints
- add tenant and user danger actions with idempotency, audit logging, and throttling
- isolate platform routes into routes/api_platform.php with dedicated bootstrap registration
- include focused platform read and danger-action coverage

Validated with focused platform superadmin feature tests.
```