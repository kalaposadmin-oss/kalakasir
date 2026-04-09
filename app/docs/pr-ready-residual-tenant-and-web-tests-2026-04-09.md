# PR Ready - Residual Tenant and Web Tests 2026-04-09

## Judul PR

`test(app): add residual tenant scoping and web coverage`

## Fokus Reviewer

- pastikan setiap test benar-benar memukul boundary tenant/role yang dimaksud, bukan hanya happy path kosong
- pastikan smoke tests mengunci route middleware dan affordance view yang memang penting untuk portal/backoffice
- pastikan coverage reversal inventory dan sales summary tetap sejalan dengan engine inventory tunggal

## Validasi

- `tests: all`