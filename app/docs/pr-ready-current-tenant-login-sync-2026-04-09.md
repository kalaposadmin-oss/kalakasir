# PR Ready Current Tenant Login Sync 2026-04-09

## PR Title

Sync current tenant on web login

## Commit Message

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

## PR Description

```markdown
## Scope

This PR isolates current-tenant synchronization on web login. It keeps session domain policy, tenant switching UX, and onboarding flow work out of scope.

## Included Files

- app/Listeners/SetCurrentTenantOnLogin.php
- app/Providers/AppServiceProvider.php
- database/migrations/2026_03_03_000000_add_current_tenant_id_to_users_table.php
- tests/Feature/CurrentTenantLoginSyncTest.php

## What Changed

- add the current_tenant_id user column
- register a login listener that repairs missing or invalid current tenant state
- store current_tenant_id in the web session during login

## Validation

- php artisan test tests/Feature/CurrentTenantLoginSyncTest.php passed

## Explicitly Excluded

- shared session domain and cookie changes
- tenant switching UX and onboarding flows
```