# Rebuild / Repair Governance

## Scope
Command berisiko tinggi:
- `inventory:rebuild-valuation`
- `inventory:rollback-valuation-method`
- `transactions:backfill-transaction-date`
- `inventory:benchmark-rebuild`

## Governance Rules
- Wajib `--tenant_id` explicit (tidak boleh fallback tenant pertama)
- Wajib explicit `--confirm=<ACK>` untuk command destruktif
- Dilarang eksekusi lintas semua tenant sekaligus dari command manual
- Wajib audit log untuk eksekusi success/failure

## Maintenance Window
- Jalankan hanya dalam window terjadwal.
- Freeze write API tenant terkait saat rebuild/rollback.
- Pastikan queue worker health hijau sebelum eksekusi.

## Approval Process
- Dua orang approval (operator + reviewer)
- Tiket perubahan wajib berisi:
  - alasan
  - tenant
  - expected blast radius
  - rollback plan

## Post-Execution Verification
1. `GET /api/v1/valuation/reconciliation`
2. `php artisan inventory:audit-valuation --tenant_id=<UUID> --limit=20`
3. Review `valuation_audit_logs` dan `anomalies`
