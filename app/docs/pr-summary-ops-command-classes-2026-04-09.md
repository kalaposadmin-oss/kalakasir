# PR Summary Ops Command Classes 2026-04-09

## Scope

PR ini hanya membawa dua class command operasional yang masih orphan di worktree beserta focused command tests. Route/API/web surface lain tetap di luar scope.

## Included Files

- `app/Console/Commands/PurgeAuditLogsCommand.php`
- `app/Console/Commands/EnforceSubscriptionLifecycleCommand.php`
- `tests/Feature/PurgeAuditLogsCommandTest.php`
- `tests/Feature/SubscriptionLifecycleCommandTest.php`

## What Changed

- menambahkan `audit:purge` untuk purge `audit_logs` dan `subscription_audit_logs` di luar retention window, dengan guard `--force`
- menambahkan `subscriptions:enforce-lifecycle` untuk menerapkan pending plan change dan transisi status subscription berdasarkan due invoice, trial end, dan grace period

## Validation

- `php artisan test tests/Feature/PurgeAuditLogsCommandTest.php tests/Feature/SubscriptionLifecycleCommandTest.php` passed
- focused run mencakup force guard purge, selective row deletion, pending plan application, tenant scoping, dan transisi `past_due` ke `suspended`

## Explicitly Excluded

- `routes/console.php`
- workflow deploy staging
- feature controller atau middleware batch lain