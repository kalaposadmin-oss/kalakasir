# Hotfix Tenant Default Role Parse 2026-04-08

## Scope

Hotfix ini hanya memperbaiki parse error PHP pada `app/Services/TenantDefaultRoleSetupService.php`.

## Problem

- File mengandung karakter liar `ker` sebelum tag `<?php`.
- Akibatnya PHP gagal parse dengan error:
  - `Namespace declaration statement has to be the very first statement or after any declare call in the script`
- Error ini memblok `php artisan test` meskipun tidak terkait langsung dengan batch web-route.

## Change

- Hapus karakter liar sebelum tag pembuka PHP di `app/Services/TenantDefaultRoleSetupService.php`.

## Validation

- `get_errors` pada `app/Services/TenantDefaultRoleSetupService.php` bersih.
- `php artisan test tests/Feature/PortalWebAccessTest.php` kembali bisa jalan sukses setelah hotfix ini.

## Suggested Commit Message

`fix(app): remove stray characters from tenant default role setup service`

## Actual Commit

- `3fe4b86` `fix(app): remove stray characters from tenant default role setup service`

## Exact Stage List

Jika hotfix ini dipisah dari PR web-route, stage hanya file berikut:

- `app/Services/TenantDefaultRoleSetupService.php`
- `docs/hotfix-tenant-default-role-parse-2026-04-08.md`

Jangan ikutkan `docs/pending-pr-backlog-2026-04-08.md` bila ingin hotfix tetap kecil dan bersih.

## Exact Git Commands

Setelah PR web-route selesai di-commit atau index sudah dikosongkan, gunakan:

```powershell
git add app/Services/TenantDefaultRoleSetupService.php docs/hotfix-tenant-default-role-parse-2026-04-08.md
git commit -m "fix(app): remove stray characters from tenant default role setup service"
```