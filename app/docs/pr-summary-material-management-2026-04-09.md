# PR Summary Material Management 2026-04-09

## Scope

PR ini hanya membawa material management surface dasar: schema, model, API CRUD tenant-safe, web view bahan, dan route materials. Discount, loyalty, variant, register, dan checkout flow tetap di luar scope.

## Included Files

- `app/Http/Controllers/Api/MaterialController.php`
- `app/Models/Material.php`
- `database/migrations/2026_03_03_000002_create_materials_table.php`
- `database/migrations/2026_03_03_000003_add_cost_and_supplier_to_materials_table.php`
- `resources/views/materials.blade.php`
- material import + route lines di `routes/api.php`
- `tests/Feature/MaterialManagementApiTest.php`

## What Changed

- menambahkan tabel `materials` beserta field `unit_cost` dan `supplier_id`
- menambahkan model `Material` dengan cast `is_active` dan `unit_cost`
- menambahkan endpoint list/create/show/update/delete bahan yang tenant-safe dan role-guarded untuk admin/owner/superadmin
- mengaktifkan halaman web `/backoffice/materials` dengan UI CRUD yang memanggil API materials dan supplier list

## Validation

- `php artisan test tests/Feature/MaterialManagementApiTest.php` passed
- focused run mencakup tenant scoping, create/show/update/delete, validasi supplier lintas tenant, dan role guard

## Explicitly Excluded

- `DiscountCalculationService`
- `Discount` model dan migration discount
- loyalty accounts
- product variant/register schema
- checkout discount flow