# Backend POS Architecture - Ringkasan Lengkap

Tanggal: 2026-03-09

Dokumen ini merangkum hasil diskusi desain backend utama aplikasi POS (Point of Sale) yang multi-user, multi-role, multi-outlet, dan multi-tenant. Fokus pada arsitektur backend, data model, alur transaksi, dan best practice sebelum coding.

## 1) Ringkasan Arsitektur
- Satu backend utama, satu database utama, banyak user.
- Tenant = merchant/perusahaan, outlet = cabang di bawah tenant.
- Akses dibedakan dengan authentication, role, permission, tenant, dan outlet.
- REST API sebagai antarmuka utama.
- Modular monolith sebagai pilihan terbaik untuk tahap awal-menengah.
- Transaksi penjualan harus atomik (ACID), dengan ledger stok dan audit log.

## 2) Evaluasi Pendekatan Satu Backend
Pendekatan ini benar dan scalable jika:
- Semua tabel bisnis memiliki tenant_id.
- Transaksi terikat outlet_id dan created_by.
- Checkout memiliki boundary transaksi dan locking stok.
- Stock ledger (stock_movements) dan audit log selalu dicatat.

## 3) Modul Inti Backend POS
Modul inti + tambahan yang disepakati:
- auth
- users
- roles
- permissions
- tenants/merchants
- outlets
- registers/cashier_stations
- categories
- products
- product_variants
- pricing/price_lists
- inventory
- stock_movements
- suppliers
- customers
- loyalty/points
- sales
- sale_items
- payments
- payment_methods
- discounts
- promotions
- vouchers
- taxes
- shifts
- cash_flows
- refunds
- reports
- settings
- audit_logs
- notifications/webhooks

Tambahan disarankan:
- devices/terminals
- receivables/payables
- gift_cards/store_credit
- integrations/accounting_export

## 4) Rekomendasi Arsitektur
Pilihan: Modular Monolith
- Alasan teknis: konsistensi data, lebih mudah menjaga ACID, cepat dikembangkan.
- Kelebihan: deployment sederhana, debugging mudah, transaksi terkontrol.
- Kekurangan: scaling per modul lebih sulit.
- Migrasi microservices saat domain sangat besar, tim besar, atau kebutuhan scaling terpisah.

## 5) Struktur Modul Backend
- Domain-centric modular architecture.
- Setiap modul berisi controller, service, repository, DTO/validator, model/entity, policy/guard.
- Checkout memiliki transaction boundary.
- Aktivitas penting ditulis ke event log dan audit log.
- Ledger stok dipisahkan dari stok summary.

## 6) Struktur Folder Project
Contoh struktur folder modular:

src/
  common/
    middleware/
    validators/
    utils/
    constants/
    events/
  auth/
  users/
  roles/
  permissions/
  tenants/
  outlets/
  registers/
  categories/
  products/
  pricing/
  inventory/
  stock_movements/
  suppliers/
  customers/
  loyalty/
  sales/
  payments/
  discounts/
  promotions/
  vouchers/
  taxes/
  shifts/
  cash_flows/
  refunds/
  reports/
  settings/
  audit/
  notifications/
  integrations/
  database/

## 7) ERD Ringkas (Relasi Kunci)
Relasi kunci yang disepakati:
- USERS -> SALES (creates)
- REGISTERS -> SALES (processes)
- OUTLETS -> INVENTORY
- PRODUCT_VARIANTS -> INVENTORY
- SALES/REFUNDS/PURCHASES -> STOCK_MOVEMENTS
- SUPPLIERS -> PURCHASES -> PURCHASE_ITEMS

## 8) Konsep Database
- Satu database cukup selama semua tabel bisnis memiliki tenant_id.
- Outlet terikat ke tenant.
- Tabel transaksi wajib memiliki outlet_id dan created_by.
- FK dibuat jelas, umumnya ON DELETE RESTRICT untuk transaksi.
- Index utama: tenant_id, outlet_id, created_at.

### Tabel Wajib (inti)
- tenants, outlets, registers
- users, roles, permissions, user_roles, role_permissions
- products, product_variants, categories
- price_lists, pricing_rules
- customers, suppliers, loyalty_accounts
- inventory, stock_movements
- sales, sale_items, payments, payment_methods
- discounts, promotions, vouchers, tax_lines
- refunds, refund_items
- purchases, purchase_items
- shifts, cash_flows
- audit_logs, notifications

## 9) Strategi Stok
- Kolom stock di product tidak cukup.
- Gunakan stock_movements sebagai ledger sumber kebenaran.
- inventory.stock_on_hand hanya summary dan dihitung dari ledger.
- Gunakan row locking saat checkout.
- Refund dan cancellation dilakukan dengan reversal movement.

## 10) Alur Sistem (Ringkas)
1. Login user
2. Pilih outlet/register
3. Buat draft sale
4. Add item
5. Validasi harga, diskon, promo
6. Checkout (transaction boundary)
7. Simpan pembayaran
8. Catat stock movements (OUT)
9. Update inventory
10. Commit + audit log
11. Laporan tersaji dari data transaksi

## 11) Endpoint REST (Ringkas)
Contoh endpoint inti:
- POST /auth/login
- POST /sales/draft
- POST /sales/{id}/items
- POST /sales/{id}/checkout
- POST /refunds
- POST /purchases/{id}/receive
- GET /reports/sales

## 12) Checkout Flow (Pseudocode)
- Lock sale + inventory rows
- Validate pricing + stock
- Create payments
- Update sale to PAID
- Create stock_movements (OUT)
- Update inventory
- Audit log
- Commit

## 13) Audit & Event Log
- audit_logs: siapa melakukan apa, kapan, pada entity apa.
- event_logs (opsional): untuk proses async (notifikasi, webhooks).

## 14) Best Practice
- Performa: index tenant_id/outlet_id/created_at, cache master data.
- Keamanan: RBAC, token expiry pendek, rate limit.
- Validasi: semua diskon dan harga dihitung di backend.
- Concurrency: SELECT FOR UPDATE pada inventory.
- Observability: metrics, tracing, structured logs.
- Backup: harian + point-in-time recovery.
- Deployment: rolling/blue-green.

## 15) Rekomendasi Final Sebelum Coding
- Finalisasi schema + index + FK.
- Pastikan ledger stok + audit log dari awal.
- Definisikan permission matrix per role.
- Buat test flow utama: checkout, refund, purchase receive.
