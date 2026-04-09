# Backend Readiness Checklist (Full Stack)

Dokumen ini merangkum kesiapan backend untuk seluruh aplikasi: modul inti, security, observability, data, QA, dan deployment.

## Cara pakai
- Status: Done / Partial / TODO
- Target rilis: semua item wajib minimal Done atau punya mitigasi yang tertulis.

## Ringkasan Status
- Estimasi kesiapan total: 75-80%
- Fokus sisa: deployment, observability, security hardening, data migration SOP.

## 1) Modul Inti POS
- [x] Sale/Checkout (Done)
- [x] Inventory ledger & valuation (Done)
- [x] Purchase & receiving (Done)
- [x] Refund & void (Done)
- [x] Shift & cash control (Done)
- [x] Reporting dasar (Done)
- [ ] Edge-case flow lintas modul (Partial)

## 2) Subscription & Billing
- [x] Plan S/M/P + entitlements (Done)
- [x] Plan change + audit log (Done)
- [x] Proration & schedule policy (Done)
- [x] Invoice & mark paid (Done)
- [ ] Downgrade/upgrade massal + simulasi beban (Partial)

## 3) Security & Access
- [x] Role gate & feature gate (Done)
- [x] Tenant isolation (Done)
- [ ] Rate limit & abuse protection (Partial)
- [ ] Security headers & CSP baseline (Partial)
- [ ] Token/session hygiene (Partial)

## 4) Observability & Ops
- [x] Anomaly detectors dasar (Done)
- [ ] Centralized log & log retention (Partial)
- [ ] Alerting channel (Partial)
- [ ] SLO/SLA dashboard (Partial)
- [ ] Incident runbook lengkap (Partial)

## 5) Data & Migration
- [x] Migrations & seeders (Done)
- [ ] Data migration SOP (Partial)
- [ ] Backfill rules & validation report (Partial)
- [ ] Rollback plan data (Partial)

## 6) QA & Test
- [x] Unit tests (Done)
- [x] Feature tests utama (Done)
- [ ] Load/perf test baseline (Partial)
- [ ] E2E smoke test otomatis (Partial)

## 7) Deployment & CI/CD
- [x] CI tests (Done)
- [ ] CD workflow + env secrets (Partial)
- [ ] Deployment checklist (Partial)
- [ ] Rollback checklist (Partial)

## Exit Criteria (Go-Live)
- Semua item security + observability minimal Partial dengan mitigasi tertulis.
- Deployment SOP + rollback SOP tervalidasi minimal 1 kali di staging.
- Load test baseline tercatat untuk 1-2 skenario utama.
