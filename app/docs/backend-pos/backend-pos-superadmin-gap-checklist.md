# Backend POS - Superadmin Gap Checklist

Tanggal: 2026-03-10
Status: Draft

Tujuan dokumen ini adalah memetakan endpoint spec superadmin terhadap implementasi yang sudah ada, berikut target controller/method dan komponen pendukungnya.

Legenda status:
- exists: sudah ada dan sesuai
- partial: ada tapi belum sesuai spec
- missing: belum ada

## A) Platform Health

| Endpoint | Status | Controller Target | Method Target | Request Class | Resource/Transformer | Service | Migration Need | Priority |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| GET /api/v1/platform/health | exists | (routes closure) | n/a | n/a | n/a | n/a | none | low |

## B) Dashboard

| Endpoint | Status | Controller Target | Method Target | Request Class | Resource/Transformer | Service | Migration Need | Priority |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| GET /api/v1/platform/dashboard | exists | Platform/DashboardController | index | PlatformDashboardRequest | PlatformDashboardResource | PlatformDashboardService | none | high |

## C) Tenants

| Endpoint | Status | Controller Target | Method Target | Request Class | Resource/Transformer | Service | Migration Need | Priority |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| GET /api/v1/platform/tenants | exists | Platform/TenantController | index | PlatformTenantIndexRequest | TenantSummaryResource | PlatformTenantService | none | high |
| GET /api/v1/platform/tenants/{tenant_id} | exists | Platform/TenantController | show | PlatformTenantShowRequest | TenantDetailResource | PlatformTenantService | none | high |
| POST /api/v1/platform/tenants/{tenant_id}/suspend | exists | Platform/TenantController | suspend | PlatformTenantActionRequest | ActionStatusResource | PlatformTenantService | none | high |
| POST /api/v1/platform/tenants/{tenant_id}/unsuspend | exists | Platform/TenantController | unsuspend | PlatformTenantActionRequest | ActionStatusResource | PlatformTenantService | none | high |
| POST /api/v1/platform/tenants/{tenant_id}/impersonate | exists | Platform/TenantController | impersonate | PlatformTenantActionRequest | ImpersonateResource | PlatformTenantService | none | high |
| POST /api/v1/platform/tenants/{tenant_id}/force-logout | exists | Platform/TenantController | forceLogout | PlatformTenantActionRequest | ActionStatusResource | PlatformTenantService | none | high |
| POST /api/v1/platform/tenants/{tenant_id}/reset-owner-password | exists | Platform/TenantController | resetOwnerPassword | PlatformTenantActionRequest | ActionStatusResource | PlatformTenantService | none | high |
| POST /api/v1/platform/tenants/{tenant_id}/unlock | exists | Platform/TenantController | unlock | PlatformTenantActionRequest | ActionStatusResource | PlatformTenantService | none | high |

## D) Global Users

| Endpoint | Status | Controller Target | Method Target | Request Class | Resource/Transformer | Service | Migration Need | Priority |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| GET /api/v1/platform/users | exists | Platform/UserController | index | PlatformUserIndexRequest | UserSummaryResource | PlatformUserService | none | high |
| GET /api/v1/platform/users/{user_id} | exists | Platform/UserController | show | PlatformUserShowRequest | UserDetailResource | PlatformUserService | none | high |
| GET /api/v1/platform/users/{user_id}/audit-logs | exists | Platform/UserController | auditLogs | PlatformUserAuditLogRequest | AuditLogResource | PlatformUserService | none | medium |
| POST /api/v1/platform/users/{user_id}/reset-password | exists | Platform/UserController | resetPassword | PlatformUserActionRequest | ActionStatusResource | PlatformUserService | none | high |
| POST /api/v1/platform/users/{user_id}/force-logout | exists | Platform/UserController | forceLogout | PlatformUserActionRequest | ActionStatusResource | PlatformUserService | none | high |
| POST /api/v1/platform/users/{user_id}/unlock | exists | Platform/UserController | unlock | PlatformUserActionRequest | ActionStatusResource | PlatformUserService | none | high |

## E) Billing

| Endpoint | Status | Controller Target | Method Target | Request Class | Resource/Transformer | Service | Migration Need | Priority |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| GET /api/v1/platform/invoices | exists | Platform/InvoiceController | index | PlatformInvoiceIndexRequest | InvoiceSummaryResource | PlatformInvoiceService | none | medium |
| GET /api/v1/platform/invoices/{invoice_id} | exists | Platform/InvoiceController | show | PlatformInvoiceShowRequest | InvoiceDetailResource | PlatformInvoiceService | none | medium |
| GET /api/v1/platform/plan-changes | exists | Platform/PlanChangeController | index | PlatformPlanChangeIndexRequest | PlanChangeResource | PlatformPlanChangeService | none | medium |
| GET /api/v1/platform/plan-changes/export | exists | Platform/PlanChangeController | export | PlatformPlanChangeExportRequest | n/a (csv) | PlatformPlanChangeService | none | medium |
| GET /api/v1/platform/subscription/audit-logs/export | exists | SubscriptionController | exportAuditLogsCsvPlatform | SubscriptionAuditLogExportRequest | n/a (csv) | SubscriptionService | none | low |

## F) Monitoring

| Endpoint | Status | Controller Target | Method Target | Request Class | Resource/Transformer | Service | Migration Need | Priority |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| GET /api/v1/platform/audit-logs | exists | Platform/AuditLogController | index | PlatformAuditLogIndexRequest | AuditLogResource | PlatformAuditLogService | none | medium |
| GET /api/v1/platform/audit-logs/export | exists | Platform/AuditLogController | export | PlatformAuditLogExportRequest | n/a (csv) | PlatformAuditLogService | none | medium |
| GET /api/v1/platform/anomalies | exists | Platform/AnomalyController | index | PlatformAnomalyIndexRequest | AnomalyResource | PlatformAnomalyService | none | medium |
| POST /api/v1/platform/anomalies/{id}/resolve | exists | Platform/AnomalyController | resolve | PlatformAnomalyResolveRequest | ActionStatusResource | PlatformAnomalyService | none | medium |

## G) Support

| Endpoint | Status | Controller Target | Method Target | Request Class | Resource/Transformer | Service | Migration Need | Priority |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| GET /api/v1/platform/support/actions | exists | Platform/SupportController | actions | PlatformSupportActionIndexRequest | SupportActionResource | PlatformSupportService | none | low |
| GET /api/v1/platform/support/search | exists | Platform/SupportController | search | PlatformSupportSearchRequest | SupportSearchResource | PlatformSupportService | none | low |

## H) Notes

- "Subscription audit logs export" sudah ada sebagai endpoint platform, tetapi name/struktur file mengikuti SubscriptionController. Bisa dipertahankan atau dipindah ke Platform/PlanChangeController saat refactor.
- Semua request class, resource, dan service di atas adalah rencana struktur. Detail implementasi disesuaikan saat scaffold.
- Priority mengikuti urutan implementasi yang disepakati: dashboard, tenants, users, invoices, audit logs, danger actions, anomalies, support.
- Seluruh endpoint platform di checklist ini sudah diimplementasikan di backend (status: exists).
