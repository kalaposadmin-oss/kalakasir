# Commit Message Observability Anomaly 2026-04-09

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