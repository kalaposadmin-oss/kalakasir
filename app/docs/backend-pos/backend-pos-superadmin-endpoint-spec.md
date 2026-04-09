# Backend POS - Superadmin Endpoint Mapping Spec

Tanggal: 2026-03-10
Status: Implemented (verified 2026-03-12)

Dokumen ini mendefinisikan detail request/response untuk endpoint superadmin.
Format response mengikuti standar `data/meta` dan error payload.

## 0) Standar Umum

### 0.1) Success
```json
{
  "data": { ... },
  "meta": { "page": 1, "per_page": 20, "total": 120, "last_page": 6, "request_id": "req-123" }
}
```

### 0.2) Error
```json
{
  "code": "FORBIDDEN",
  "message": "Tidak memiliki akses.",
  "details": {},
  "request_id": "req-123"
}
```

### 0.3) Validation Error (422)
```json
{
  "code": "VALIDATION_ERROR",
  "message": "Data tidak valid.",
  "details": {
    "reason": ["Reason minimal 10 karakter."]
  },
  "request_id": "req-123"
}
```

### 0.4) Query Params Umum
- `page` (int, default 1)
- `per_page` (int, default 20, max 100)
- `q` (string, search)
- `from` / `to` (date)

### 0.5) Pola Param Tanggal
- Range umum: `from`, `to`
- Range spesifik: `{field}_from`, `{field}_to` (dipakai bila ada >1 range)
- Contoh: `created_from`, `created_to`, `last_login_from`, `last_login_to`, `due_from`, `due_to`

### 0.6) Format Waktu
- Semua field datetime menggunakan ISO 8601 UTC.
- Contoh: `2026-03-10T08:00:00Z`

### 0.7) Header Requirements
- `Authorization: Bearer <token>`
- `X-Request-Id` (opsional, akan digenerate jika tidak ada)
- `Idempotency-Key` (wajib untuk danger actions)
- `X-Platform-RateLimit-Bypass` (opsional, internal)

### 0.8) HTTP Status Codes
- 200 OK (read/update ringan)
- 201 CREATED (create)
- 202 ACCEPTED (async)
- 400 BAD_REQUEST
- 401 UNAUTHENTICATED
- 403 FORBIDDEN
- 404 NOT_FOUND
- 409 CONFLICT
- 422 VALIDATION_ERROR
- 429 RATE_LIMITED

### 0.9) Enum Referensi
- tenant.status: active, suspended
- tenant.health: healthy, billing_issue, inactive, suspended
- anomaly.severity: low, medium, high, critical
- role: owner, admin, supervisor, cashier
- plan: S, M, P (S=starter, M=growth, P=pro)
- plan_change.mode: upgrade, downgrade, renewal, manual_override
- action_type: tenant_suspended, tenant_unsuspended, tenant_impersonated, tenant_force_logout, tenant_reset_owner, tenant_unlock, user_reset_password, user_force_logout, user_unlock, anomaly_resolved
- action_type (tambahan): tenant_impersonation_ended
- invoice.status: draft, open, paid, overdue, void
- payment.status: pending, paid, failed, refunded

### 0.10) Sorting Convention
- `sort_by` (field)
- `sort_dir` (asc, desc)

Valid `sort_by`:
- /platform/tenants: name, created_at, last_active_at, user_count
- /platform/users: name, last_login_at, created_at
- /platform/invoices: due_at, amount, status, created_at
- /platform/plan-changes: created_at, mode
- /platform/payments: paid_at, amount, status, created_at
- /platform/audit-logs: created_at, event_type
- /platform/anomalies: detected_at, severity
- /platform/support/actions: created_at, action_type

### 0.11) Boolean Query Params
- Gunakan `true|false` (bukan 1/0).

### 0.12) Pagination Empty Convention
```json
{
  "data": { "items": [] },
  "meta": { "page": 1, "per_page": 20, "total": 0, "last_page": 1, "request_id": "req-123" }
}
```

### 0.13) Invalid Sort/Filter (422)
```json
{
  "code": "VALIDATION_ERROR",
  "message": "Data tidak valid.",
  "details": {
    "sort_by": ["sort_by tidak valid."]
  },
  "request_id": "req-123"
}
```

### 0.14) Conflict (409) - Contoh
```json
{
  "code": "CONFLICT",
  "message": "Tenant sudah suspended.",
  "details": {},
  "request_id": "req-123"
}
```

### 0.15) Release References
- Changelog implementasi: `docs/backend-pos/backend-pos-superadmin-implementation-changelog-2026-03-12.md`
- Checklist rilis: `docs/backend-pos/backend-pos-superadmin-release-checklist-2026-03-12.md`
- Release note (non-teknis): `docs/backend-pos/backend-pos-superadmin-release-note-2026-03-12.md`
- Template PR final: `docs/backend-pos/backend-pos-superadmin-pr-template-2026-03-12.md`
- Draft PR description (siap pakai): `docs/backend-pos/backend-pos-superadmin-pr-description-draft-2026-03-12.md`
- PR title + commit bundle: `docs/backend-pos/backend-pos-superadmin-pr-title-and-commit-bundle-2026-03-12.md`
- Final recommendation (title + squash): `docs/backend-pos/backend-pos-superadmin-pr-final-recommendation-2026-03-12.md`

## 1) Dashboard Platform

### GET /platform/dashboard
Query Params:
- `preset` (today, last7, last30)
- `from` (date)
- `to` (date)

Response:
```json
{
  "data": {
    "totals": {
      "tenant_total": 0,
      "tenant_active": 0,
      "tenant_suspended": 0,
      "user_total": 0
    },
    "overdue_invoices": 0,
    "recent_events": [
      {
        "id": "uuid",
        "tenant_id": "uuid",
        "old_plan": "S",
        "new_plan": "M",
        "reason": "Upgrade kebutuhan fitur.",
        "request_id": "req-123",
        "created_at": "2026-03-10T08:00:00Z"
      }
    ],
    "health_summary": {
      "healthy": 0,
      "billing_issue": 0,
      "inactive": 0
    }
  },
  "meta": { "request_id": "req-123" }
}
```

## 2) Tenants

### GET /platform/tenants
Query Params:
- `page`, `per_page`, `q`
- `status` (active, suspended)
- `plan` (S, M, P)
- `health` (healthy, billing_issue, suspended, inactive)
- `created_from`, `created_to` (date)
- `sort_by`, `sort_dir`

Response:
```json
{
  "data": {
    "tenants": [
      {
        "tenant_id": "uuid",
        "name": "Tenant A",
        "plan": "S",
        "status": "active",
        "health": "healthy",
        "created_at": "2026-03-01T10:00:00Z",
        "last_active_at": "2026-03-10T08:00:00Z",
        "user_count": 5,
        "outlet_count": 2,
        "plan_expires_at": null,
        "storage_usage": null
      }
    ]
  },
  "meta": { "page": 1, "per_page": 20, "total": 1, "last_page": 1, "request_id": "req-123" }
}
```

### GET /platform/tenants/{tenant_id}
Query Params:
- `preset` (today, last7, last30)
- `from`, `to`

Response:
```json
{
  "data": {
    "tenant": { "tenant_id": "uuid", "name": "Tenant A", "status": "active", "plan": "S" },
    "usage_summary": { "orders": 0, "revenue": 0 },
    "user_count": 5,
    "owner_exists": true,
    "users_preview": [
      {
        "user_id": "uuid",
        "name": "Owner",
        "email": "owner@x.com",
        "phone": null,
        "role": "owner",
        "status": "active",
        "last_login_at": "2026-03-10T08:00:00Z",
        "created_at": "2026-03-01T08:00:00Z",
        "is_owner": true
      }
    ],
    "recent_events": [
      {
        "id": "uuid",
        "old_plan": "S",
        "new_plan": "M",
        "reason": "Upgrade kebutuhan fitur.",
        "request_id": "req-123",
        "created_at": "2026-03-10T08:00:00Z"
      }
    ]
  },
  "meta": { "request_id": "req-123" }
}
```

### POST /platform/tenants/{tenant_id}/suspend
Body:
- `reason` (string, min 10)

Conflict:
- 409 jika subscription tidak ditemukan.
- 409 jika tenant sudah suspended.

### POST /platform/tenants/{tenant_id}/unsuspend
Body:
- `reason` (string, min 10)

Conflict:
- 409 jika subscription tidak ditemukan.
- 409 jika tenant tidak dalam status suspended.

### POST /platform/tenants/{tenant_id}/impersonate
Body:
- `reason` (string, min 10)

Not found:
- 404 jika tenant tidak ditemukan.

Catatan Impersonate:
- Wajib reason dan audit log.
- Session impersonation memiliki expiry 30 menit.
- UI wajib menampilkan banner bahwa admin sedang impersonate.
- Saat selesai (manual stop atau expired), sistem mencatat audit event `tenant_impersonation_ended`.
- Expiry otomatis terjadi di middleware jika `expires_at` sudah lewat.
- Payload audit berisi `reason`, `started_at`, `ended_at`, dan `request_id`.

Impersonate response (success):
```json
{
  "data": {
    "status": "ok",
    "action": "tenant_impersonated",
    "tenant_id": "uuid",
    "impersonation_expires_at": "2026-03-10T09:00:00Z"
  },
  "meta": { "request_id": "req-123" }
}
```

### POST /platform/tenants/{tenant_id}/force-logout
Body:
- `reason` (string, min 10)

### POST /platform/tenants/{tenant_id}/reset-owner-password
Body:
- `reason` (string, min 10)

Not found:
- 404 jika owner tidak ditemukan.

### POST /platform/tenants/{tenant_id}/unlock
Body:
- `reason` (string, min 10)

Danger action response (success):
```json
{
  "data": {
    "status": "ok",
    "action": "tenant_suspended",
    "tenant_id": "uuid"
  },
  "meta": { "request_id": "req-123" }
}
```

## 3) Global Users

### GET /platform/users
Query Params:
- `page`, `per_page`, `q`
- `tenant_id`, `role`, `status`
- `last_login_from`, `last_login_to`
- `sort_by`, `sort_dir`

Response (single-tenant model):
```json
{
  "data": {
    "users": [
      {
        "user_id": "uuid",
        "name": "User A",
        "email": "a@x.com",
        "phone": "0812",
        "tenant_id": "uuid",
        "tenant_name": "Tenant A",
        "role": "admin",
        "status": "active",
        "last_login_at": "2026-03-10T08:00:00Z"
      }
    ]
  },
  "meta": { "page": 1, "per_page": 20, "total": 1, "last_page": 1, "request_id": "req-123" }
}
```

### GET /platform/users/{user_id}
Response:
```json
{
  "data": {
    "user": {
      "user_id": "uuid",
      "name": "User A",
      "email": "a@x.com",
      "role": "admin",
      "status": "active",
      "last_login_at": "2026-03-10T08:00:00Z",
      "created_at": "2026-03-01T08:00:00Z"
    },
    "tenants": []
  },
  "meta": { "request_id": "req-123" }
}
```

### GET /platform/users/{user_id}/audit-logs
Query Params:
- `page`, `per_page`, `from`, `to`, `q`
Response:
```json
{
  "data": {
    "audit_logs": [
      {
        "id": "uuid",
        "tenant_id": "uuid",
        "event_type": "user_force_logout",
        "reference_type": "user",
        "reference_id": "uuid",
        "payload_json": {
          "reason": "Keamanan akun.",
          "request_id": "req-123"
        },
        "created_at": "2026-03-10T08:00:00Z"
      }
    ]
  },
  "meta": { "page": 1, "per_page": 20, "total": 0, "last_page": 1, "request_id": "req-123" }
}
```

### POST /platform/users/{user_id}/reset-password
### POST /platform/users/{user_id}/force-logout
### POST /platform/users/{user_id}/unlock
Body:
- `reason` (string, min 10)

User action response (success):
```json
{
  "data": {
    "status": "ok",
    "action": "user_reset_password",
    "user_id": "uuid"
  },
  "meta": { "request_id": "req-123" }
}
```

## 4) Billing

### GET /platform/invoices
Query Params:
- `page`, `per_page`, `q`
- `tenant_id`, `status`, `due_from`, `due_to`
- `sort_by`, `sort_dir`

Catatan status:
- Internal `unpaid` dipetakan sebagai `open` di response.

Response:
```json
{
  "data": {
    "invoices": [
      {
        "invoice_id": "uuid",
        "tenant_id": "uuid",
        "tenant_name": "Tenant A",
        "number": "INV-0001",
        "status": "open",
        "amount": 250000,
        "amount_paid": 0,
        "due_at": "2026-03-15T00:00:00Z",
        "created_at": "2026-03-10T08:00:00Z"
      }
    ]
  },
  "meta": { "page": 1, "per_page": 20, "total": 0, "last_page": 1, "request_id": "req-123" }
}
```

### GET /platform/invoices/{invoice_id}
Response:
```json
{
  "data": {
    "invoice": {
      "invoice_id": "uuid",
      "tenant_id": "uuid",
      "number": "INV-0001",
      "status": "open",
      "amount": 250000,
      "amount_paid": 0,
      "issued_at": "2026-03-10T08:00:00Z",
      "due_at": "2026-03-15T00:00:00Z",
      "paid_at": null,
      "created_at": "2026-03-10T08:00:00Z"
    },
    "items": [
      {
        "id": 1,
        "description": "Subscription March",
        "qty": 1,
        "unit_price": 250000,
        "total_price": 250000
      }
    ]
  },
  "meta": { "request_id": "req-123" }
}
```

### GET /platform/plan-changes
Query Params:
- `page`, `per_page`, `tenant_id`, `mode`, `from`, `to`, `q`
- `sort_by`, `sort_dir`

Response:
```json
{
  "data": {
    "plan_changes": [
      {
        "id": "uuid",
        "tenant_id": "uuid",
        "old_plan": "S",
        "new_plan": "M",
        "mode": "upgrade",
        "effective_at": "2026-03-10T08:00:00Z",
        "proration_amount": 150000,
        "reason": "Upgrade kebutuhan fitur.",
        "request_id": "req-123",
        "created_at": "2026-03-10T08:00:00Z"
      }
    ]
  },
  "meta": { "page": 1, "per_page": 20, "total": 0, "last_page": 1, "request_id": "req-123" }
}
```

### GET /platform/plan-changes/export
Query Params:
- `tenant_id`, `mode`, `from`, `to`, `q`
Response:
- Content-Type: text/csv
- File name pattern: `plan-changes-YYYY-MM-DD.csv`
- CSV columns: `id`, `created_at`, `tenant_id`, `old_plan`, `new_plan`, `mode`, `actor_id`, `reason`, `request_id`

### GET /platform/payments
Query Params:
- `page`, `per_page`, `q`
- `tenant_id`, `status`, `paid_from`, `paid_to`
- `sort_by`, `sort_dir`

Response:
```json
{
  "data": {
    "payments": [
      {
        "payment_id": "uuid",
        "tenant_id": "uuid",
        "tenant_name": "Tenant A",
        "invoice_id": "uuid",
        "invoice_number": "INV-0001",
        "provider": "manual",
        "reference": "REF-001",
        "status": "paid",
        "amount": 250000,
        "paid_at": "2026-03-10T08:00:00Z",
        "created_at": "2026-03-10T08:00:00Z"
      }
    ]
  },
  "meta": { "page": 1, "per_page": 20, "total": 0, "last_page": 1, "request_id": "req-123" }
}
```

## 5) Monitoring

### GET /platform/health
Response:
```json
{
  "data": {
    "generated_at": "2026-03-10T08:00:00Z",
    "checks": [
      {
        "key": "db",
        "label": "Database",
        "status": "ok",
        "message": "Database normal.",
        "latency_ms": 12
      },
      {
        "key": "cache",
        "label": "Cache",
        "status": "ok",
        "message": "Cache normal.",
        "latency_ms": 1
      },
      {
        "key": "storage",
        "label": "Storage",
        "status": "ok",
        "message": "Storage dapat ditulis."
      },
      {
        "key": "queue",
        "label": "Queue",
        "status": "warn",
        "message": "Queue masih menggunakan driver sync.",
        "backlog": 0,
        "failed_jobs": 0
      },
      {
        "key": "response_latency",
        "label": "Response Latency",
        "status": "ok",
        "message": "Latency dihitung dari proses health check.",
        "latency_ms": 49
      }
    ],
    "metrics": {
      "tenant_total": 10,
      "user_total": 100,
      "active_sessions": 25,
      "overdue_invoices": 3,
      "response_latency_ms": 49
    }
  },
  "meta": { "request_id": "req-123" }
}
```

### GET /platform/audit-logs
Query Params:
- `page`, `per_page`, `tenant_id`, `event_type`, `actor_id`, `from`, `to`, `q`
- `sort_by`, `sort_dir`

Response:
```json
{
  "data": {
    "audit_logs": [
      {
        "id": "uuid",
        "tenant_id": "uuid",
        "actor_id": "uuid",
        "event_type": "tenant_suspended",
        "reference_type": "tenant",
        "reference_id": "uuid",
        "payload_json": {
          "reason": "Pelanggaran kebijakan.",
          "request_id": "req-123"
        },
        "created_at": "2026-03-10T08:00:00Z"
      }
    ]
  },
  "meta": { "page": 1, "per_page": 20, "total": 0, "last_page": 1, "request_id": "req-123" }
}
```

### GET /platform/audit-logs/export
Query Params:
- `tenant_id`, `event_type`, `actor_id`, `from`, `to`, `q`
Response:
- Content-Type: text/csv
- File name pattern: `audit-logs-YYYY-MM-DD.csv`
- CSV columns: `id`, `created_at`, `event_type`, `actor_id`, `tenant_id`, `reference_type`, `reference_id`, `request_id`, `reason`

### GET /platform/anomalies
Query Params:
- `page`, `per_page`, `tenant_id`, `severity`, `resolved`, `from`, `to`
- `sort_by`, `sort_dir`

Response:
```json
{
  "data": {
    "anomalies": [
      {
        "anomaly_id": "uuid",
        "tenant_id": "uuid",
        "severity": "high",
        "type": "inventory_variance",
        "reference_id": "uuid",
        "payload_json": {},
        "detected_at": "2026-03-10T08:00:00Z",
        "resolved_at": null,
        "resolved_by": null
      }
    ]
  },
  "meta": { "page": 1, "per_page": 20, "total": 0, "last_page": 1, "request_id": "req-123" }
}
```

### POST /platform/anomalies/{id}/resolve
Body:
- `reason` (string, optional)

Anomaly resolve response (success):
```json
{
  "data": {
    "status": "ok",
    "action": "anomaly_resolved",
    "anomaly_id": "uuid"
  },
  "meta": { "request_id": "req-123" }
}
```

Conflict:
- 409 jika anomaly sudah resolved.

## 6) Support

### GET /platform/support/actions
Query Params:
- `page`, `per_page`, `tenant_id`, `actor_id`, `action_type`, `from`, `to`, `q`
- `sort_by`, `sort_dir`

Response:
```json
{
  "data": {
    "actions": [
      {
        "action_id": "uuid",
        "tenant_id": "uuid",
        "actor_id": "uuid",
        "action_type": "tenant_suspended",
        "reference_id": "uuid",
        "reason": "Pelanggaran kebijakan.",
        "request_id": "req-123",
        "created_at": "2026-03-10T08:00:00Z"
      }
    ]
  },
  "meta": { "page": 1, "per_page": 20, "total": 0, "last_page": 1, "request_id": "req-123" }
}
```

### GET /platform/support/search
Query Params:
- `q` (tenant name/id, user email/name, invoice number/id)

Response:
```json
{
  "data": {
    "matches": [
      { "type": "tenant", "id": "uuid", "label": "Tenant A" },
      { "type": "user", "id": "uuid", "label": "user@x.com" },
      { "type": "invoice", "id": "uuid", "label": "INV-0001" }
    ]
  },
  "meta": { "request_id": "req-123" }
}
```

## 6.1) Platform Settings

### GET /platform/settings/feature-flags
Query Params:
- `tenant_id` (optional)

Response:
```json
{
  "data": {
    "feature_flags": [
      {
        "key": "pos.checkout",
        "module": "pos",
        "uiType": "page",
        "defaultVisible": true,
        "plans": ["S", "M", "P"]
      }
    ],
    "plan_labels": { "S": "Starter", "M": "Medium", "P": "Pro" },
    "tenant": { "tenant_id": "uuid", "name": "Tenant A", "plan": "M" },
    "entitlements": [
      { "key": "pos.checkout", "module": "pos", "ui_type": "page", "default_visible": true, "plans": ["S","M","P"], "allowed": true }
    ]
  },
  "meta": { "request_id": "req-123" }
}
```

### GET /platform/settings/rate-limits
Response:
```json
{
  "data": {
    "api_per_minute": 120,
    "sensitive_per_minute": 30,
    "platform_danger_per_hour": 10,
    "platform_rate_limit_override_enabled": true,
    "idempotency_ttl_minutes": 60,
    "idempotency_lock_seconds": 10
  },
  "meta": { "request_id": "req-123" }
}
```

## 7) Audit & Rate Limit Policy

### 7.1) Audit Log Fields (Danger Actions)
- `actor_id`
- `action_type`
- `tenant_id` or `user_id`
- `reason`
- `request_id`
- `created_at`

### 7.2) Rate Limit
- Max 10 actions / admin / hour
- Applied to: impersonate, force logout, reset password, reset owner, unlock
- Override internal: header `X-Platform-RateLimit-Bypass` (token cocok `PLATFORM_RATE_LIMIT_OVERRIDE_TOKEN`)

### 7.3) Idempotency
- `Idempotency-Key` wajib untuk:
  - suspend
  - reset-owner-password
  - reset-password
  - force-logout
  - unlock
  - anomaly resolve

### 7.4) Audit Retention
- Audit log dipurge otomatis harian berdasarkan `AUDIT_LOG_RETENTION_DAYS`.
