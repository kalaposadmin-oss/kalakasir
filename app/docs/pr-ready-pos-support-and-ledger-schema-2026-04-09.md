# PR Ready - POS Support and Ledger Schema 2026-04-09

## Judul PR

`feat(app): add pos support and ledger hardening schema`

## Fokus Reviewer

- pastikan unique key `opening_key` di stock ledger tidak memblokir row non-opening yang memang nullable
- pastikan tabel POS baru tetap tenant-scoped dan foreign key memakai `restrictOnDelete`
- pastikan kolom biaya tambahan di stock ledger konsisten dengan arah pengembangan valuation dan tidak mengubah flow lama secara diam-diam

## Validasi

- `tests: all`