# PR Ready Observability Anomaly 2026-04-09

## PR Title

Harden observability anomaly filters and batch context

## Commit Message

```text
feat(app): harden observability anomaly filters and context

Improve the tenant-safe observability and anomaly surface used by the
backoffice observability page.

- add branch, location, and reference filters for observability APIs
- redact global queue health counters while keeping tenant analytics
- audit anomaly resolution actions
- expand the observability page with batch variance quick filters and context

Validated with focused observability API and web access tests.
```

## PR Description

```markdown
## Scope

This PR isolates observability and anomaly surface changes needed by the backoffice observability page. It keeps detector jobs, listeners, and unrelated catalog work out of scope.

## Included Files

- app/Http/Controllers/Api/ObservabilityController.php
- app/Http/Controllers/Api/AnomalyController.php
- resources/views/observability.blade.php
- observability and anomalies route lines in routes/api.php
- tests/Feature/AnomalyObservabilityApiTest.php
- tests/Feature/ObservabilityTenantScopingTest.php

## What Changed

- add branch, location, and reference filters to observability and anomaly APIs
- redact global queue counters from the tenant health payload
- write audit logs when anomalies are resolved
- expand the observability page with branch/location filters and production batch variance quick context

## Validation

- php artisan test tests/Feature/AnomalyObservabilityApiTest.php tests/Feature/ObservabilityTenantScopingTest.php passed
- php artisan test tests/Feature/WebSettingsFinanceSmokeTest.php --filter=observability passed
- php artisan test tests/Feature/PortalWebAccessTest.php --filter=observability passed

## Explicitly Excluded

- detector and reconciliation job changes
- listener and event plumbing outside anomaly resolution
- discount, loyalty, variant, and register work
```