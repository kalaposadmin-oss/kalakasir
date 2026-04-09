# Data Migration & Rollback SOP

Dokumen ini menjelaskan SOP migrasi data produksi dan rollback.

## Pra-Migrasi
- [ ] Backup database penuh (snapshot)
- [ ] Export data kritikal (sales, payments, inventory)
- [ ] Validasi skema target
- [ ] Dry run di staging

## Proses Migrasi
1) Freeze write (maintenance mode)
2) Jalankan migrasi schema
3) Jalankan backfill data (batch)
4) Jalankan validation checks
5) Unfreeze write

## Validasi Minimum
- Row count per tabel inti
- Sum total sales & payments
- Balance inventory per location
- Sample data random

## Rollback SOP
1) Stop write (maintenance)
2) Restore snapshot
3) Re-run health check
4) Inform stakeholders

## Catatan
- Semua migrasi harus idempotent.
- Jika ditemukan mismatch, rollback segera sebelum traffic normal.
