# Commit Message Current Tenant Login Sync 2026-04-09

```text
feat(app): sync current tenant on web login

Ensure web login keeps the user's current tenant selection aligned with the
primary tenant and stores it in session state.

- add current_tenant_id to users
- register a login listener that repairs missing or invalid tenant state
- persist current_tenant_id into the web session on login
- include focused login-sync coverage

Validated with focused current-tenant login tests.
```