# PR Ready - QA Portal Helper Script 2026-04-09

## Judul PR

`chore(app): add portal qa user helper script`

## Fokus Reviewer

- pastikan script hanya dipakai untuk dev/QA dan tidak mengubah runtime production
- pastikan helper idempotent saat user sudah ada
- pastikan role assignment memakai tenant pertama yang resolved dari database lokal

## Validasi

- `ensure_portal_qa_users.php`