# PR Ready - Platform and POS Seeders 2026-04-09

## Judul PR

`test(app): add platform and pos seeders`

## Fokus Reviewer

- pastikan `PlatformSuperadminSeeder` idempotent dan tidak menggandakan role assignment
- pastikan `PosMasterSeeder` hanya melengkapi data tenant pertama yang sudah ada, bukan mereset data operasional
- pastikan update referensi variant/register/payment method dilakukan aman untuk row lama yang masih null

## Validasi

- `PlatformSuperadminSeeder`
- `PosMasterSeeder`