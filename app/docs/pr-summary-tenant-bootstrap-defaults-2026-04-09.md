# PR Summary Tenant Bootstrap Defaults 2026-04-09

## Scope

PR ini hanya membawa service bootstrap default tenant untuk accounting, branch/location default, dan owner provisioning onboarding. Listener login dan flow onboarding halaman tetap di luar scope.

## Included Files

- `app/Services/TenantDefaultAccountingSetupService.php`
- `app/Services/TenantDefaultOperationalSetupService.php`
- `app/Services/OnboardingProvisioningService.php`
- `database/migrations/2026_03_03_000006_add_default_location_id_to_tenants_table.php`
- `tests/Feature/TenantBootstrapSetupServicesTest.php`

## What Changed

- menambahkan seed idempotent default chart of accounts dan account mapping tenant
- menambahkan bootstrap branch + default location beserta pointer `tenants.default_location_id`
- menambahkan provisioning service untuk melengkapi company profile onboarding: create/update tenant, create owner, assign owner role, setup operational default, dan setup accounting default

## Validation

- `php artisan test tests/Feature/TenantBootstrapSetupServicesTest.php` passed
- focused run mencakup accounting bootstrap idempotent, default branch/location bootstrap, dan owner provisioning onboarding

## Explicitly Excluded

- `SetCurrentTenantOnLogin`
- `OnboardingInitialProductService`
- `OnboardingFlowStateService`
- controller/view onboarding