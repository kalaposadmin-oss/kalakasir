# Commit Message Web Consumer Pages 2026-04-09

## Subject

`feat(app): add subscription support and marketing portal pages`

## Body

```text
Add the remaining consumer-facing backoffice pages for subscription,
settings support, and marketing flows.

- add subscription current, change, features, history, and invoices pages
- add preferences and integrations settings pages
- add help center, about, support, and marketing pages

Validation:
- php artisan test tests/Feature/WebSubscriptionSupportSmokeTest.php tests/Feature/WebSupportMarketingSmokeTest.php
```