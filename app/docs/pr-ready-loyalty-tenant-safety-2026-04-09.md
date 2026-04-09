# PR Ready Loyalty Tenant Safety 2026-04-09

## PR Title

Harden loyalty tenant scoping and page actions

## Commit Message

```text
feat(app): harden loyalty tenant scoping and actions

Tighten the loyalty API and loyalty backoffice page so they respect tenant
boundaries and only expose management actions to elevated roles.

- scope loyalty tiers, customers, and sales to the active tenant
- hide loyalty management actions from non-admin backoffice roles
- align the loyalty page with the current backoffice shell
- include focused tenant-scoping coverage for the loyalty endpoint

Validated with focused loyalty, smoke, and portal access tests.
```

## PR Description

```markdown
## Scope

This PR isolates tenant-safety and role-aware UI hardening for the loyalty surface. It keeps loyalty accounts, vouchers, and reward workflows out of scope.

## Included Files

- app/Http/Controllers/Api/LoyaltyController.php
- resources/views/loyalty.blade.php
- tests/Feature/LoyaltyTenantScopingTest.php

## What Changed

- scope loyalty tiers, customers, and sales to the active tenant
- prevent non-admin backoffice roles from seeing loyalty management actions
- align the loyalty page header and shell with the current backoffice navigation

## Validation

- php artisan test tests/Feature/LoyaltyTenantScopingTest.php passed
- php artisan test tests/Feature/WebSupportMarketingSmokeTest.php passed
- php artisan test tests/Feature/PortalWebAccessTest.php passed

## Explicitly Excluded

- loyalty accounts and voucher flows
- reward ledger and referral runtime logic
```