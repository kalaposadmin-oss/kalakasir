# Backend POS - Kebutuhan UI Superadmin (Platform Admin)

Tanggal: 2026-03-10
Status: Draft

Dokumen ini mendefinisikan kebutuhan UI/flow untuk superadmin (platform) yang dapat melihat semua tenant dan user lintas toko.

## 1) Struktur Menu Superadmin
- Dashboard
  - Platform Dashboard
- Tenant Management
  - Tenants
  - Tenant Detail
- User Management
  - Global Users
  - User Detail
- Billing
  - Invoices
  - Plan Changes
  - Payment Status
- Monitoring
  - Audit Logs
  - Anomalies
  - System Health
- Support
  - Support Search
  - Support Action History
- Platform Settings
  - Feature Flags (perlu validasi)
  - Security & Rate Limits (perlu validasi)

Catatan:
- Aksi berbahaya (suspend, impersonate, force logout, reset owner, unlock) berada di halaman detail (tenant/user) sebagai Danger Zone, bukan menu utama.

## 2) Rincian Per Halaman

Format: Nama Halaman — Tujuan | Fitur Utama | Aksi | Filter/Search/Sort | Tabel/Kolom | Modal/Drawer/Form | State | Validasi | Permission

### A) Dashboard Platform
- Tujuan: ringkasan kesehatan seluruh sistem dan KPI platform.
- Fitur: KPI total tenant, tenant aktif, tenant suspended, total user, invoice overdue, recent platform events.
- Aksi: klik ke detail tenant, export summary.
- Filter/Search/Sort: range waktu (hari/minggu/bulan), region (perlu validasi).
- Tabel/Kolom: recent incidents, top tenants by usage.
- Modal/Drawer: detail incident.
- State: loading KPI, empty list, error fetch.
- Validasi: none.
- Permission: Superadmin.
- Acceptance Criteria:
  - KPI total tenant, aktif, suspended, dan total user tampil pada load pertama.
  - Widget overdue invoice menampilkan jumlah yang konsisten dengan list billing.
  - Recent platform events tampil dan bisa diklik ke detail terkait.
- Endpoint (perlu validasi):
  - GET /platform/dashboard
  - Query Params: range (preset), from, to
  - Response:
    - data: { totals, overdue_invoices, recent_events, health_summary }
    - meta: { request_id }

### B) Tenants - Daftar Tenant
- Tujuan: melihat seluruh tenant, status, plan, dan health.
- Fitur: list tenant + quick actions.
- Aksi: view detail, suspend/unsuspend, change plan, export.
- Filter/Search/Sort: search (name/id), status (active/suspended), plan (S/M/P), created_at, last_active.
- Tabel/Kolom:
  - tenant_id
  - nama
  - plan
  - status
  - created_at
  - last_active_at (perlu validasi)
  - user_count (perlu validasi)
- Modal/Drawer/Form:
  - suspend reason
  - change plan (reason wajib)
- State: loading, empty, error.
- Validasi:
  - reason min length (plan change)
  - confirm suspend.
- Permission: Superadmin.
- Acceptance Criteria:
  - Superadmin melihat daftar tenant dengan pagination server-side.
  - Filter status dan plan bekerja bersamaan.
  - Pencarian tenant by name/id mengembalikan hasil relevan.
  - Suspend membutuhkan konfirmasi dan alasan.
  - Perubahan status tercatat pada audit log.
- Endpoint:
  - GET /platform/tenants
  - Query Params:
    - page
    - per_page
    - q
    - status
    - plan
    - created_from
    - created_to
    - health (healthy/billing_issue/suspended/inactive)
  - Response:
    - data.tenants[]:
      - tenant_id
      - name
      - plan
      - status
      - health
      - created_at
      - last_active_at
      - user_count
      - outlet_count
      - plan_expires_at
      - storage_usage (opsional)
    - meta: page, per_page, total, last_page

### C) Tenants - Detail Tenant
- Tujuan: insight lengkap tenant.
- Fitur: profile tenant, usage summary, user list ringkas, recent sales/activities (perlu validasi data).
- Aksi: update status, change plan, danger actions (impersonate, reset owner, force logout, unlock).
- Filter/Search/Sort: date range untuk aktivitas.
- Tabel/Kolom: user list ringkas (name, role, last_login), recent events.
- Modal/Drawer: confirm danger actions (alasan wajib).
- State: loading/error/empty.
- Validasi: confirm action berisiko.
- Permission: Superadmin.
- Acceptance Criteria:
  - Detail tenant menampilkan profil, status, plan, dan ringkasan aktivitas.
  - Danger actions berada di section terpisah dan memerlukan konfirmasi + alasan.
  - Change plan menyimpan audit log dengan actor dan reason.
- Endpoint:
  - GET /platform/tenants/{tenant_id}
  - Query Params: range (preset), from, to
  - Response:
    - data.tenant
    - data.usage_summary
    - data.users_preview[]
    - data.recent_events[]
    - meta
- Danger Actions (perlu validasi):
  - POST /platform/tenants/{tenant_id}/suspend
  - POST /platform/tenants/{tenant_id}/unsuspend
  - POST /platform/tenants/{tenant_id}/impersonate
  - POST /platform/tenants/{tenant_id}/force-logout
  - POST /platform/tenants/{tenant_id}/reset-owner
  - POST /platform/tenants/{tenant_id}/unlock

### D) Global Users - Daftar User
- Tujuan: melihat semua user lintas tenant.
- Fitur: list + filter tenant.
- Aksi: view detail, reset password, deactivate (perlu validasi).
- Filter/Search/Sort: tenant, role, status, last_login, search name/email/phone.
- Tabel/Kolom:
  - user_id
  - name
  - email/phone
  - tenant_name
  - role
  - status
  - last_login_at (perlu validasi)
- Modal/Drawer: reset password, deactivate reason.
- State: loading, empty, error.
- Validasi: confirm reset.
- Permission: Superadmin.
- Acceptance Criteria:
  - Daftar user menampilkan informasi tenant dan role per user.
  - Filter tenant + role bekerja bersamaan.
  - Search nama/email/phone mengembalikan hasil relevan.

Catatan definisi (perlu validasi):
- Apakah 1 user bisa multi-tenant atau 1 user = 1 tenant membership.
- Jika multi-tenant, list menampilkan membership per tenant (user_id + tenant_id).
- Endpoint:
  - GET /platform/users
  - Query Params:
    - page
    - per_page
    - q
    - tenant_id
    - role
    - status
    - last_login_from
    - last_login_to
  - Response:
    - data.users[] (atau data.memberships[] untuk multi-tenant)
    - meta: page, per_page, total, last_page

### E) Global Users - Detail User
- Tujuan: detail profil user + akses lintas tenant.
- Fitur: profile, audit log user, tenant mapping.
- Aksi: reset password, force logout.
- Filter/Search/Sort: audit log range.
- Tabel/Kolom: audit log (event, time, tenant).
- Modal/Drawer: confirm reset.
- State: loading, error.
- Validasi: confirm.
- Permission: Superadmin.
- Acceptance Criteria:
  - Detail user menampilkan profil dan daftar tenant terkait.
  - Reset password dan force logout memerlukan konfirmasi.
- Endpoint:
  - GET /platform/users/{user_id}
  - Response: data.user, data.tenants, data.audit_logs
- Danger Actions (perlu validasi):
  - POST /platform/users/{user_id}/reset-password
  - POST /platform/users/{user_id}/force-logout
  - POST /platform/users/{user_id}/unlock

### F) Billing & Subscription - Invoices (Global)
- Tujuan: melihat semua invoice lintas tenant.
- Fitur: list invoice + status.
- Aksi: view detail, export.
- Filter/Search/Sort: tenant, status, due_date, range.
- Tabel/Kolom:
  - invoice_id
  - tenant
  - amount
  - status
  - due_at
- Modal/Drawer: invoice detail.
- State: loading, empty, error.
- Validasi: none (read-only MVP).
- Permission: Superadmin.
- Acceptance Criteria:
  - List invoice global read-only dengan pagination.
  - Filter tenant/status/due_date bekerja.
  - Detail invoice dapat dibuka dari list.
- Endpoint:
  - GET /platform/invoices
  - Query Params: page, per_page, tenant_id, status, due_from, due_to, q
  - Response: data.invoices[], meta
  - GET /platform/invoices/{invoice_id}

### G) Billing & Subscription - Plan Changes (Global)
- Tujuan: melihat riwayat perubahan plan semua tenant.
- Fitur: audit log plan change.
- Aksi: export.
- Filter/Search/Sort: tenant, mode, date range.
- Tabel/Kolom: old_plan, new_plan, actor, reason, created_at.
- State: loading/empty/error.
- Validasi: none.
- Permission: Superadmin.
- Acceptance Criteria:
  - List plan change dapat difilter per tenant dan mode.
  - Export menghasilkan CSV sesuai filter.
- Endpoint:
  - GET /platform/plan-changes
  - Query Params: page, per_page, tenant_id, mode, from, to, q
  - Response: data.plan_changes[], meta
  - GET /platform/plan-changes/export

### H) Audit & Observability - Audit Logs (Global)
- Tujuan: monitoring kejadian penting lintas tenant.
- Fitur: list audit log.
- Aksi: export CSV.
- Filter/Search/Sort: tenant, event_type, actor_id, date range, q.
- Tabel/Kolom: event_type, actor, tenant, request_id, created_at.
- State: loading/empty/error.
- Validasi: none.
- Permission: Superadmin.
- Acceptance Criteria:
  - Audit logs dapat difilter per tenant, event_type, actor_id.
  - Export CSV sesuai filter aktif.
- Endpoint:
  - GET /platform/audit-logs
  - Query Params: page, per_page, tenant_id, event_type, actor_id, from, to, q
  - Response: data.audit_logs[], meta
  - GET /platform/audit-logs/export

### I) Audit & Observability - Anomalies (Global)
- Tujuan: memantau anomaly lintas tenant.
- Fitur: list anomaly, resolve.
- Aksi: resolve, filter severity.
- Filter/Search/Sort: tenant, severity, resolved, range.
- Tabel/Kolom: type, severity, detected_at, tenant, status.
- Modal: confirm resolve.
- State: loading/empty/error.
- Validasi: confirm.
- Permission: Superadmin.
- Acceptance Criteria:
  - Anomaly list menampilkan status resolved/open.
  - Resolve membutuhkan konfirmasi.
- Endpoint:
  - GET /platform/anomalies
  - Query Params: page, per_page, tenant_id, severity, resolved, from, to
  - Response: data.anomalies[], meta
  - POST /platform/anomalies/{id}/resolve

### J) Support
- Tujuan: pusat informasi aksi support dan pencarian kasus.
- Fitur: support search, recent support actions, action history.
- Aksi: lihat detail aksi (read-only).
- Filter/Search/Sort: tenant, actor, date range, action type.
- Tabel/Kolom: action_type, tenant, actor, reason, created_at.
- State: loading, empty, error.
- Validasi: none.
- Permission: Superadmin only.
- Acceptance Criteria:
  - Daftar aksi support tampil dengan pagination.
  - Filter tenant + action type bekerja.
- Endpoint:
  - GET /platform/support/actions
  - Query Params: page, per_page, tenant_id, actor_id, action_type, from, to, q
  - Response: data.actions[], meta
  - GET /platform/support/search
  - Query Params: q (tenant name/id, user email/phone, invoice id)
  - Response: data.matches[]

## 3) Prioritas Implementasi
- MVP Superadmin:
  - Tenants list + detail
  - Global users list
  - Billing invoices global (read-only)
  - Audit logs global
- Operasional:
  - Plan change global
  - Anomalies global
  - Support (action history)
- Opsional/Fase berikut:
  - Feature flags
  - Region-based analytics
  - Advanced health dashboard

## 4) Flow Utama (Superadmin)
- Tenant Management:
  - Dashboard -> Tenants list -> Tenant detail -> (suspend/change plan/impersonate)
- Billing:
  - Billing list -> Invoice detail (read-only MVP)
- Support:
  - Tenant detail -> Danger Zone action -> Confirm action -> Log audit

## 5) Komponen UI Reusable
- Data table + server pagination (wajib pakai data/meta)
- Filter bar + chips + search
- Bulk actions (suspend, export)
- Confirm modal (danger action)
- Drawer detail (tenant/user/invoice)
- Toast (success/error)
- Empty state / loading skeleton
- Permission guard (superadmin only)

## 6) Dashboard Platform - Widget/KPI
- Total tenants
- Tenant aktif
- Tenant suspended
- Total users
- Invoice overdue count
- Recent platform events

Phase 2:
- MRR/ARR (perlu validasi sumber data)
- Churn rate (perlu validasi)
- Anomaly count (open)

Tambahan (opsional, Phase 2):
- Active tenants last 24h
- New tenants last 7 days
- Failed payments
- Top tenants by usage

## 7) Ketergantungan API data/meta
- Semua list global menggunakan response `data/meta`:
  - Tenants list
  - Users list
  - Invoices list
  - Audit logs
  - Anomalies
- Pagination wajib baca meta: page, per_page, total, last_page.
- Error handling wajib baca `code/message/details/request_id`.

## 7.1) Audit Policy (Danger Actions)
- Semua danger actions wajib menulis audit log:
  - actor_id
  - action_type
  - tenant_id / user_id
  - reason
  - request_id
  - created_at

## 7.2) Rate Limit (Danger Actions)
- Batasan awal (perlu validasi): max 10 action / admin / jam
- Berlaku untuk: impersonate, force logout, reset password, reset owner, unlock

## 8) Matriks Role vs Menu (Superadmin)
- Superadmin: akses penuh semua menu platform.
- Admin tenant (non-platform): tidak dapat mengakses menu platform.

## 9) Checklist Handoff
- Mapping endpoint superadmin (perlu validasi)
- Definisi filter/sort untuk list global
- Komponen reusable siap
- Empty/loading/error state didefinisikan
- Permission guard diterapkan
- Audit log untuk aksi support

## 10) Area Perlu Validasi
- Ketersediaan endpoint global tenants/users/invoices/audit/anomalies
- Field usage metrics (last_active_at, user_count, MRR)
- Aksi support yang legal (impersonate, force logout, reset owner)
- Definisi Global Users: single-tenant vs multi-tenant membership

## 11) Tambahan Struktur Database (Perlu Validasi)
- platform_admins
- platform_audit_logs
- tenant_usage_metrics
- tenant_plan_changes
- support_actions
- system_incidents
