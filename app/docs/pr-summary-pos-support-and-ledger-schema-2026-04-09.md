# PR Summary - POS Support and Ledger Schema 2026-04-09

## Ringkasan

Batch ini menutup sisa schema POS/komersial dan ledger yang dibutuhkan oleh flow pricing, voucher, cash movement, loyalty, notifications, settings, serta opening stock yang lebih aman.

## Cakupan

- kolom `unit_cost`, `total_cost`, dan `opening_key` pada `stock_ledger`
- tabel `price_lists` dan `pricing_rules`
- tabel `vouchers`, `tax_lines`, `cash_flows`, `notifications`, `loyalty_accounts`, dan `settings`

## Nilai Perubahan

- ledger awal stok punya key unik untuk mencegah duplikasi opening balance
- flow POS punya schema dasar untuk pricing bertingkat, voucher, kas shift, loyalty, notifikasi, dan pengaturan tenant
- service, seeder, dan smoke flow yang sudah ada sekarang punya fondasi tabel yang lengkap

## Validasi

- `tests: all` -> `560 passed (3375 assertions)`