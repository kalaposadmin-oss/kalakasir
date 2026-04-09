# PR Summary Loyalty Tenant Safety 2026-04-09

## Scope

PR ini hanya membawa hardening loyalty surface: tenant scoping pada endpoint loyalty dan pembatasan aksi manajemen di halaman loyalty berdasarkan role web. Loyalty account schema, voucher, dan reward workflow tetap di luar scope.

## Included Files

- `app/Http/Controllers/Api/LoyaltyController.php`
- `resources/views/loyalty.blade.php`
- `tests/Feature/LoyaltyTenantScopingTest.php`

## What Changed

- membatasi query tier, customer, dan sales loyalty ke tenant aktif
- memastikan loyalty aggregate tidak bocor dari sale tenant lain walau customer id overlap di payload uji
- menyesuaikan halaman loyalty agar hanya admin/owner/superadmin yang melihat aksi kelola tier dan tambah member
- menyelaraskan loyalty page dengan backoffice shell dan menu button yang konsisten

## Validation

- `php artisan test tests/Feature/LoyaltyTenantScopingTest.php` passed
- `php artisan test tests/Feature/WebSupportMarketingSmokeTest.php` passed
- `php artisan test tests/Feature/PortalWebAccessTest.php` passed

## Explicitly Excluded

- loyalty accounts migration dan reward ledger
- voucher flow dan referral reward runtime
- pricing, checkout, dan membership management CRUD