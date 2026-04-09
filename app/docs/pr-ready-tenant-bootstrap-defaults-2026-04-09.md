# PR Ready Tenant Bootstrap Defaults 2026-04-09

## PR Title

Add tenant bootstrap default setup services

## Commit Message

```text
feat(app): add tenant bootstrap default setup services

Add the tenant bootstrap defaults used during onboarding and first operational
setup.

- add default accounting setup service for initial account and mapping bootstrap
- add default branch/location setup service with tenant default location pointer
- add onboarding provisioning service for tenant owner bootstrap

Validated with focused tenant bootstrap service tests.
```

## PR Description

```markdown
## Scope

This PR isolates the default tenant bootstrap services used during onboarding and first-time setup. It intentionally keeps login listener wiring, initial product bootstrap, and onboarding page/controller flow out of scope.

## Included Files

- app/Services/TenantDefaultAccountingSetupService.php
- app/Services/TenantDefaultOperationalSetupService.php
- app/Services/OnboardingProvisioningService.php
- database/migrations/2026_03_03_000006_add_default_location_id_to_tenants_table.php
- tests/Feature/TenantBootstrapSetupServicesTest.php

## What Changed

- add idempotent default chart-of-accounts and account-mapping bootstrap
- add branch/location default setup and tenant default_location_id persistence
- add onboarding provisioning service to create/update tenant owner and bootstrap defaults

## Validation

- php artisan test tests/Feature/TenantBootstrapSetupServicesTest.php passed

## Explicitly Excluded

- SetCurrentTenantOnLogin
- OnboardingInitialProductService
- onboarding controllers and views
```