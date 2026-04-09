# PR Ready Ops Command Classes 2026-04-09

## PR Title

Add audit retention and subscription lifecycle commands

## Commit Message

```text
feat(ops): add audit retention and subscription lifecycle commands

Add the remaining command class slice for audit log retention and subscription
lifecycle enforcement.

- add audit:purge with explicit force guard and retention-based deletion
- add subscriptions:enforce-lifecycle for pending plan application and status transitions
- include focused command coverage for purge safety and lifecycle state changes

Validated with focused command feature tests.
```

## PR Description

```markdown
## Scope

This PR isolates two operational command classes that were still outside the previous ops route/workflow slice. It intentionally keeps console route wiring, deploy workflow, and feature controllers out of scope.

## Included Files

- app/Console/Commands/PurgeAuditLogsCommand.php
- app/Console/Commands/EnforceSubscriptionLifecycleCommand.php
- tests/Feature/PurgeAuditLogsCommandTest.php
- tests/Feature/SubscriptionLifecycleCommandTest.php

## What Changed

- add audit:purge to remove old audit_logs and subscription_audit_logs rows with an explicit --force guard
- add subscriptions:enforce-lifecycle to apply due pending plan changes and enforce trial/grace/invoice-based status transitions
- add focused command tests for purge safety, tenant scoping, pending plan application, and suspension transition behavior

## Validation

- php artisan test tests/Feature/PurgeAuditLogsCommandTest.php tests/Feature/SubscriptionLifecycleCommandTest.php passed

## Explicitly Excluded

- routes/console.php
- deploy workflow
- unrelated feature batches
```