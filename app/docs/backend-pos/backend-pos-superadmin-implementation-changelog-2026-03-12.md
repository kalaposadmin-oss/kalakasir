# Backend POS - Superadmin Implementation Changelog

Tanggal: 2026-03-12
Status: Implemented and validated
Scope: Superadmin API platform module, security hardening, and regression stabilization.

## 1) Summary

Dokumen ini merangkum perubahan yang sudah diimplementasikan terhadap spec superadmin endpoint dan hasil verifikasi regresi.

## 2) Delivered Changes

### 2.1 API Coverage

Implemented endpoints:
- GET /api/v1/platform/dashboard
- GET /api/v1/platform/tenants
- GET /api/v1/platform/tenants/{tenant_id}
- POST /api/v1/platform/tenants/{tenant_id}/suspend
- POST /api/v1/platform/tenants/{tenant_id}/unsuspend
- POST /api/v1/platform/tenants/{tenant_id}/impersonate
- POST /api/v1/platform/tenants/{tenant_id}/force-logout
- POST /api/v1/platform/tenants/{tenant_id}/reset-owner-password
- POST /api/v1/platform/tenants/{tenant_id}/unlock
- GET /api/v1/platform/users
- GET /api/v1/platform/users/{user_id}
- GET /api/v1/platform/users/{user_id}/audit-logs
- POST /api/v1/platform/users/{user_id}/reset-password
- POST /api/v1/platform/users/{user_id}/force-logout
- POST /api/v1/platform/users/{user_id}/unlock
- GET /api/v1/platform/invoices
- GET /api/v1/platform/invoices/{invoice_id}
- GET /api/v1/platform/plan-changes
- GET /api/v1/platform/plan-changes/export
- GET /api/v1/platform/payments
- GET /api/v1/platform/health
- GET /api/v1/platform/audit-logs
- GET /api/v1/platform/audit-logs/export
- GET /api/v1/platform/anomalies
- POST /api/v1/platform/anomalies/{id}/resolve
- GET /api/v1/platform/support/actions
- GET /api/v1/platform/support/search
- GET /api/v1/platform/settings/feature-flags
- GET /api/v1/platform/settings/rate-limits

### 2.2 Response Contract Alignment

Completed:
- Datetime normalized to ISO 8601 UTC on platform read responses.
- Pagination and request validation aligned for listing endpoints.
- Support action filtering constrained to allowed action_type enum.

### 2.3 Security and Operational Hardening

Completed:
- Idempotency store hardened using atomic lock + cache strategy.
- Redis preferred in production for idempotency storage.
- Danger action rate limit and API/sensitive rate limit configurable via platform config.
- Audit retention purge command implemented and scheduled.

### 2.4 New Backoffice/Platform Support Components

Completed:
- Platform payment listing service/controller/request.
- Platform settings service/controller/request for feature flags and rate limits snapshot.
- Additional middleware and routing guard improvements integrated in web and API flows.

## 3) Configuration Changes

Added in platform config:
- api_rate_limit_per_minute
- sensitive_rate_limit_per_minute
- danger_rate_limit_per_hour
- idempotency_ttl_minutes
- idempotency_lock_seconds

Operational note:
- In production, Redis should be available for stable idempotent danger action handling.

## 4) Test Validation

Focused suites passed:
- PlatformSuperadminReadApiTest
- PlatformSuperadminDangerActionsTest
- tests: valuation
- tests: write-flow

Full regression status:
- 127 passed
- 524 assertions
- Duration: 16.83s
- Last run date: 2026-03-12

## 5) Known Risks and Follow-ups

Residual risks:
- Environment drift if Redis is missing in production while idempotency load is high.
- API contract drift if future endpoints are added without request/resource parity checks.

Recommended follow-ups:
- Add CI job to enforce contract checks for data/meta envelope consistency.
- Add smoke probes for danger action idempotency replay behavior.
- Keep release checklist mandatory before production rollout.
