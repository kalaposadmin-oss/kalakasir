## Summary
- repair legacy user role resolution by rebuilding missing tenant role assignments from normalized legacy role values
- ensure onboarding and staff management continue to promote the first tenant user to Owner when no owner role assignment exists yet
- cover onboarding owner bootstrap and role fallback behavior with focused onboarding and tenant bootstrap tests

## Testing
- php artisan test tests/Feature/OnboardingFirstTimeFlowTest.php tests/Feature/TenantBootstrapSetupServicesTest.php --compact
