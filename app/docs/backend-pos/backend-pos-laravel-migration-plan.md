# Backend POS - Laravel Migration Plan (PostgreSQL)

Catatan:
- Repository ini sudah memiliki banyak migration yang relevan.
- Dokumen ini memberi panduan mapping dan tambahan agar konsisten dengan desain POS.
- Gunakan timestamptz dan jsonb bila menggunakan PostgreSQL.

## 1) Urutan Migration Disarankan
1. tenants, outlets, registers
2. users, roles, permissions, user_roles, role_permissions
3. categories, products, product_variants
4. price_lists, pricing_rules
5. customers, suppliers, loyalty_accounts
6. inventory, stock_movements
7. sales, sale_items, payment_methods, payments
8. discounts, promotions, vouchers, tax_lines
9. refunds, refund_items
10. purchases, purchase_items
11. shifts, cash_flows
12. audit_logs, notifications

## 2) Pola Field Wajib
- id: $table->bigIncrements('id');
- tenant_id: $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
- timestamps: $table->timestampsTz();
- jsonb: $table->jsonb('payload_json')->nullable();

## 3) Contoh Skeleton Migration
```php
Schema::create('inventory', function (Blueprint $table) {
    $table->bigIncrements('id');
    $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
    $table->foreignId('outlet_id')->constrained('outlets')->restrictOnDelete();
    $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
    $table->integer('stock_on_hand')->default(0);
    $table->timestampsTz();
    $table->unique(['outlet_id', 'product_variant_id']);
    $table->index(['tenant_id', 'outlet_id']);
});
```

## 4) Mapping ke Migration Eksisting
Periksa migration berikut untuk memastikan field sesuai desain:
- tenants, roles, permissions, user_roles, role_permissions
- branches atau outlets (pastikan tenant_id dan outlet_id)
- inventory_balances atau inventory (pastikan stock_on_hand dan unique index)
- stock_ledger (pastikan ref_type, ref_id, qty, outlet_id)
- sales, sale_items, payments

Jika ada perbedaan, buat migration tambahan untuk menambahkan:
- index komposit (tenant_id, outlet_id, created_at)
- kolom created_by di sales
- kolom register_id di sales
- kolom outlet_id di transaksi penting

## 5) Checklist Postgres
- pastikan jsonb untuk payload
- pastikan timestampsTz()
- pastikan indeks komposit sesuai kebutuhan query
