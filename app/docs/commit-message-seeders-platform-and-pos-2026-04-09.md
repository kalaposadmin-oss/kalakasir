# Commit Message Platform and POS Seeders 2026-04-09

## Subject

`test(app): add platform and pos seeders`

## Body

```text
Add local and QA seeders for platform superadmin access and richer POS demo data.

- add idempotent platform superadmin seeder
- add POS master seeder for registers, variants, pricing, vouchers, and related support data
- keep seeders safe for repeated local setup

Validation:
- php artisan db:seed --class=PlatformSuperadminSeeder
- php artisan db:seed --class=PosMasterSeeder
```