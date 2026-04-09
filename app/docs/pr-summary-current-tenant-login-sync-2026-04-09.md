# PR Summary Current Tenant Login Sync 2026-04-09

## Scope

PR ini hanya membawa sinkronisasi `current_tenant_id` saat login web. Scope-nya dibatasi pada migration kolom user, listener login, wiring provider, dan test fokus. Session sharing cross-host dan web portal flow lain tetap di luar scope.

## Included Files

- `app/Listeners/SetCurrentTenantOnLogin.php`
- `app/Providers/AppServiceProvider.php`
- `database/migrations/2026_03_03_000000_add_current_tenant_id_to_users_table.php`
- `tests/Feature/CurrentTenantLoginSyncTest.php`

## What Changed

- menambahkan kolom `current_tenant_id` pada tabel `users`
- menambahkan listener login yang memastikan `current_tenant_id` selalu sinkron dengan tenant utama user bila belum valid
- menyimpan `current_tenant_id` ke session untuk guard web saat login berhasil
- mendaftarkan listener login pada `AppServiceProvider`

## Validation

- `php artisan test tests/Feature/CurrentTenantLoginSyncTest.php` passed

## Explicitly Excluded

- shared session domain dan cookie policy
- switching tenant lintas portal
- onboarding flow dan web portal context changes lain