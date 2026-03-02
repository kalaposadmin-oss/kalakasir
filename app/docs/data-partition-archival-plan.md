# Data Partition & Archival Plan

## Target Scale
- 1-5 juta transaksi/bulan
- Ledger/audit/anomaly tumbuh puluhan juta row per tahun

## Table Priority
1. `stock_ledger`
2. `valuation_audit_logs`
3. `anomalies`
4. `processed_events`
5. `sales` + `sale_items` (historical reads)

## Strategy
- Short-term (0-3 bulan):
  - Tambah covering index untuk query reconcile + valuation report
  - Batasi scan via date-window batching
- Mid-term (3-6 bulan):
  - Partition by month untuk `stock_ledger` dan `valuation_audit_logs`
  - Partition prune policy otomatis
- Long-term (6-12 bulan):
  - Archival cold storage (S3/object storage + manifest)
  - Read model untuk laporan historis heavy query

## Archival Policy
- `stock_ledger`: hot 12 bulan, cold >12 bulan
- `valuation_audit_logs`: hot 6 bulan, cold >6 bulan
- `anomalies`: hot 12 bulan, cold >12 bulan

## Operational Guardrails
- Setiap job reconcile harus ada parameter tanggal range.
- Query report default max range (mis. 31 hari) kecuali role khusus.
- Jalankan EXPLAIN review bulanan untuk top 20 query mahal.
