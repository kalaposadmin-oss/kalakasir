# Commit Message Web Portal Foundation Services 2026-04-09

## Subject

`feat(app): add web portal foundation services and auth views`

## Body

```text
Add the remaining service and view foundation for the portal-aware web flow.

- add portal host/session resolvers and legacy redirect helpers
- add onboarding flow state and initial product bootstrap services
- add menu registry and blade gate helpers for the web shell
- add dedicated login/logout views and owner POS management shortcuts

Validation:
- php artisan test tests/Feature/PortalWebAccessTest.php tests/Feature/WebAuthOnboardingSmokeTest.php tests/Feature/OnboardingFirstTimeFlowTest.php
```