## What Changed
- UserRoleResolverService now repairs missing user_roles assignments by reading the legacy users.role value when available and by promoting the first tenant user to Owner when no owner exists yet
- UserManagementController keeps tenant role setup in place before listing or creating staff so default role data is available consistently
- onboarding first-time flow coverage now asserts owner assignment, default branch/location bootstrap, and variant-capable initial product onboarding paths remain intact

## Validation
- focused suite passes:
  - php artisan test tests/Feature/OnboardingFirstTimeFlowTest.php tests/Feature/TenantBootstrapSetupServicesTest.php --compact

## Out Of Scope
- production batch runtime
- stock adjustment and stock transfer operational flows
- broader web portal and navigation refactors
