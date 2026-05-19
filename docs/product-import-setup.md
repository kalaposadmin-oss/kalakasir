Product Import - Setup and Queue Instructions

1) Install PhpSpreadsheet (optional, for .xlsx support)

Run in project root (server / local):

```bash
composer require phpoffice/phpspreadsheet
```

If you cannot install global composer packages on hosting, convert .xlsx to CSV locally and upload CSV.

2) Enable queue worker

By default Laravel may use `sync` driver. For background processing, set `QUEUE_CONNECTION` in `.env` to a proper driver, e.g. `database`.

- Use database driver (simple):

```bash
php artisan queue:table
php artisan migrate
```

- Start a worker (in production use a process supervisor like systemd, supervisor, or queue:listen with pm2):

```bash
# Run one worker in current terminal
php artisan queue:work --sleep=3 --tries=3
```

Or using supervisor/systemd ensure it restarts automatically.

3) Notes about storage and file cleanup

- Uploaded files are saved to `storage/app/imports` temporarily.
- `ProductImportJob` will delete the temp file on completion (`@unlink($path)`).
- Ensure `storage/` is writable by the web user.

4) Menu / UI location

- The import endpoint is available at `/backoffice/products/import` (route registered in `routes/web.php`).
- There is no automatic menu item by default. The backoffice menu is rendered from `resources/views/partials/menu-drawer.blade.php` (which includes `partials.menu-items`).
- To add a menu link, edit `backofficeMenuSections` in `resources/views/partials/menu-drawer.blade.php` and add an item with `'href' => '/backoffice/products/import'`.

5) Testing

- Upload a CSV from Backoffice → Products → Import, map columns in preview, press "Proses Import".
- Check the queue worker logs and database for created/updated products.

6) Troubleshooting

- If imports appear not to run, check `php artisan queue:failed` and worker logs.
- If `.xlsx` import fails with message about PhpSpreadsheet, run the composer require command above.
