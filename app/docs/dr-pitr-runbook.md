# DR / Backup / PITR Runbook (Production)

## Objective
- Menjamin pemulihan data keuangan dan inventory dengan target operasional:
  - RPO: <= 15 menit
  - RTO: <= 60 menit untuk API core

## Backup Strategy
- Database backup:
  - Full backup harian (retention 30 hari)
  - Incremental/WAL/binlog backup tiap 5-15 menit
- Artifact backup:
  - File report valuation, audit export, dan konfigurasi env terenkripsi
- Verification:
  - Checksum + restore test mingguan di environment staging

## PITR Requirements
- MySQL: binary log aktif + retention >= 7 hari
- PostgreSQL: WAL archiving aktif + base backup berkala
- Time sync server (NTP) wajib stabil

## Restore Drill (Weekly)
1. Pilih timestamp simulasi insiden.
2. Restore full backup terakhir ke server isolasi.
3. Replay binlog/WAL sampai timestamp target.
4. Jalankan verifikasi:
   - `php artisan inventory:audit-valuation --tenant_id=<UUID> --limit=20`
   - `php artisan test --filter='ValuationGapCoverageTest|WriteFlowLockPeriodApiEnforcementTest'`
5. Bandingkan saldo utama (cash, inventory, payable) vs snapshot kontrol.
6. Catat durasi restore dan gap data.

## Incident Procedure
1. Declare incident + freeze write traffic.
2. Ambil snapshot forensik sebelum restore.
3. Restore PITR ke timestamp aman.
4. Jalankan reconcile & smoke checks.
5. Re-open traffic bertahap.
6. Postmortem dengan root cause + action items.

## Mandatory Evidence for Audit
- Log backup sukses harian.
- Bukti restore drill mingguan.
- Bukti RPO/RTO aktual 4 minggu terakhir.
