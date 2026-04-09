# PR Ready Material Management 2026-04-09

## PR Title

Add material management API and backoffice page

## Commit Message

```text
feat(app): add material management API and backoffice page

Add the material management foundation used by the backoffice materials page.

- add materials schema and model with supplier and unit cost support
- add tenant-safe CRUD API routes and controller for materials
- add the backoffice materials page that uses the materials API
- include focused material management API coverage

Validated with focused material management feature tests.
```

## PR Description

```markdown
## Scope

This PR isolates the material management surface needed by the backoffice materials page. It intentionally keeps discount, loyalty, variant, and register work out of scope.

## Included Files

- app/Http/Controllers/Api/MaterialController.php
- app/Models/Material.php
- database/migrations/2026_03_03_000002_create_materials_table.php
- database/migrations/2026_03_03_000003_add_cost_and_supplier_to_materials_table.php
- resources/views/materials.blade.php
- materials import and routes in routes/api.php
- tests/Feature/MaterialManagementApiTest.php

## What Changed

- add materials schema and supplier/unit-cost support
- add tenant-safe CRUD API endpoints for materials with role guards
- add the backoffice materials page that consumes those endpoints

## Validation

- php artisan test tests/Feature/MaterialManagementApiTest.php passed

## Explicitly Excluded

- discount and loyalty feature work
- variant/register schema work
- checkout discount flow
```