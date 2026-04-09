## Summary
- add shared session domain inference for split portal hosts so auth sessions can move safely between pos and backoffice hosts
- make the shared app shell and menu drawer portal-aware, including back links, wide backoffice layout handling, and role-based navigation shortcuts
- wire canonical portal routes and validate them with focused portal, auth/onboarding, and dashboard smoke tests

## Testing
- php artisan test tests/Feature/PortalWebAccessTest.php tests/Feature/WebAuthOnboardingSmokeTest.php tests/Feature/WebDashboardSmokeTest.php --compact --colors=never
