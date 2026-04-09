# Backend POS - ERD Detail (Ringkas)

ERD detail berikut menjabarkan relasi utama agar siap diterjemahkan ke schema.

```mermaid
erDiagram
  TENANTS ||--o{ OUTLETS : has
  OUTLETS ||--o{ REGISTERS : has

  TENANTS ||--o{ USERS : has
  USERS }o--o{ ROLES : assigned
  ROLES }o--o{ PERMISSIONS : grants

  TENANTS ||--o{ PRODUCTS : owns
  PRODUCTS ||--o{ PRODUCT_VARIANTS : has
  PRODUCTS }o--o{ CATEGORIES : categorized

  OUTLETS ||--o{ INVENTORY : holds
  PRODUCT_VARIANTS ||--o{ INVENTORY : tracks

  OUTLETS ||--o{ SALES : creates
  USERS ||--o{ SALES : creates
  REGISTERS ||--o{ SALES : processes

  SALES ||--o{ SALE_ITEMS : contains
  SALE_ITEMS }o--o{ PRODUCT_VARIANTS : sold

  SALES ||--o{ PAYMENTS : paid_by
  PAYMENTS }o--o{ PAYMENT_METHODS : uses

  SUPPLIERS ||--o{ PURCHASES : supplies
  PURCHASES ||--o{ PURCHASE_ITEMS : contains
  PURCHASE_ITEMS }o--o{ PRODUCT_VARIANTS : restocked

  SALES ||--o{ REFUNDS : has
  REFUNDS ||--o{ REFUND_ITEMS : contains
  REFUND_ITEMS }o--o{ PRODUCT_VARIANTS : returned

  OUTLETS ||--o{ STOCK_MOVEMENTS : logs
  PRODUCT_VARIANTS ||--o{ STOCK_MOVEMENTS : moves
  SALES ||--o{ STOCK_MOVEMENTS : generates
  REFUNDS ||--o{ STOCK_MOVEMENTS : reverses
  PURCHASES ||--o{ STOCK_MOVEMENTS : adds

  TENANTS ||--o{ CUSTOMERS : has
  CUSTOMERS ||--o{ LOYALTY_ACCOUNTS : owns

  OUTLETS ||--o{ SHIFTS : runs
  SHIFTS ||--o{ CASH_FLOWS : logs

  TENANTS ||--o{ AUDIT_LOGS : records
  TENANTS ||--o{ NOTIFICATIONS : sends
```

Catatan:
- Semua tabel transaksi memiliki tenant_id dan outlet_id.
- Semua aksi penting memiliki created_by untuk audit.
- stock_movements menjadi ledger utama untuk stok.

## Lampiran Audit Index/FK
Lihat ringkasan audit index dan foreign key di [docs/backend-pos/backend-pos-index-fk-audit.md](backend-pos-index-fk-audit.md).
