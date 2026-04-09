# Backend POS - Seed Summary

Tanggal: 2026-03-09
Tenant seed: 019c9578-3f84-7209-a55f-19a32dd99b41

## Ringkasan jumlah data
- sales: 16
- sale_items: 19
- payments: 5
- discounts: 3
- taxes: 1
- tax_lines: 1
- refunds: 2
- refund_items: 2
- inventory_balances: 11
- stock_ledger: 41
- daily_sales_summaries: 1
- inventory_summaries: 11

## Catatan
- Data seed ditambahkan melalui PosMasterSeeder.
- Jalankan ulang seeder akan mengisi data yang belum ada (idempotent sebagian).
- Sinkronisasi stok dari ledger bisa dijalankan dengan pos:sync-inventory.
