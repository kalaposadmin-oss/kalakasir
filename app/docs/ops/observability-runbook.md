# Observability Runbook

Dokumen ini memuat SOP monitoring, alerting, dan respons insiden untuk backend.

## Tujuan
- Deteksi cepat gangguan produksi.
- Standarisasi respon insiden dan eskalasi.

## Sinyal Utama
- Error rate (5xx)
- Latency p95/p99 API
- Queue backlog
- Failed jobs
- DB connections & slow query
- Inventory valuation drift

## Dashboard Minimum
- API health + error rate
- Queue depth & failed jobs (5 menit)
- Database latency (p95)
- Anomaly counts per tenant

## Alert Minimum
- 5xx spike > 2% selama 5 menit
- Queue depth > 1000 selama 10 menit
- Failed jobs > 10/5 menit
- DB latency p95 > 500ms selama 10 menit

## Respons Insiden (Ringkas)
1) Identifikasi gejala (alert + logs)
2) Tentukan scope (tenant / global)
3) Mitigasi cepat (scale, disable job, throttle)
4) Root cause analysis (RCA)
5) Postmortem + action item

## Playbook Umum

### A. Queue Backlog
- Cek queue depth dan failed jobs
- Jika spike: scale worker
- Jika error berulang: pause job terkait
- Verifikasi retry policy

### B. API 5xx Spike
- Cek error log dan request pattern
- Isolasi endpoint penyebab
- Lakukan throttling / rate limit sementara

### C. Valuation Drift
- Jalankan audit job
- Review ledger dan snapshot
- Jadwalkan rebuild terkontrol

## Log Format Minimum
- timestamp
- tenant_id
- request_id
- endpoint
- status
- latency_ms
- error_code

## Escalation
- Severity High: notify on-call + product owner
- Severity Med: notify on-call
- Severity Low: triage di jam kerja

## Checklist Harian
- [ ] Monitor failed jobs
- [ ] Review anomaly summary
- [ ] Review queue depth
