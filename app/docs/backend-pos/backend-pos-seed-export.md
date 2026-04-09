# Backend POS - Seed SQL Export

Tanggal: 2026-03-09

## Perintah Export
Gunakan perintah berikut untuk mengekspor seed SQL per tenant:

```
php artisan pos:export-seed-sql --tenant_id=YOUR_TENANT_ID
```

## Output
File SQL akan tersimpan di:

```
storage/app/pos-seed.sql
```

## Reset Seed
Jika perlu reset data seed:

```
php artisan pos:reset-seed --tenant_id=YOUR_TENANT_ID --confirm=RESET_ACK
```

## Sinkronisasi Inventory
Setelah import SQL, sinkronkan stok:

```
php artisan pos:sync-inventory --tenant_id=YOUR_TENANT_ID --confirm=SYNC_ACK
```
