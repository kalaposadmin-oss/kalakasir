# Commit Message Ops Command Classes 2026-04-09

```text
feat(ops): add audit retention and subscription lifecycle commands

Add the remaining command class slice for audit log retention and subscription
lifecycle enforcement.

- add audit:purge with explicit force guard and retention-based deletion
- add subscriptions:enforce-lifecycle for pending plan application and status transitions
- include focused command coverage for purge safety and lifecycle state changes

Validated with focused command feature tests.
```