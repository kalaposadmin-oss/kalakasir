# Analisis Sistem Langganan Dan Feature Gating

Tanggal: 2026-04-11
Scope: ringkasan teknis dan bisnis berdasarkan codebase Laravel yang sudah ada
Status analisis: berbasis file, route, migration, controller, middleware, service, dan test yang terbaca di repo saat ini

Catatan pembacaan:
- Jika ada bagian yang belum ditemukan jelas di codebase, bagian itu ditandai sebagai "tidak ditemukan / belum terlihat di codebase" atau "belum pasti".
- Jika ada perbedaan antara kebutuhan bisnis dan implementasi teknis, bagian itu ditandai sebagai "inkonsistensi".
- Dokumen ini fokus pada kondisi yang SUDAH ADA, bukan usulan implementasi baru.

## 1. Ringkasan Produk

### Aplikasi ini untuk apa

Aplikasi ini adalah sistem kasir dan operasional usaha berbasis Laravel untuk mengelola:
- transaksi penjualan POS,
- pembelian dan penerimaan barang,
- stok dan pergerakan inventory,
- laporan keuangan dan analitik,
- pelanggan, supplier, user, branch, loyalty,
- subscription, billing, dan feature gating.

Dasar temuan utama:
- [routes/api.php](../routes/api.php)
- [routes/web.php](../routes/web.php)
- [app/Http/Controllers/Api](../app/Http/Controllers/Api)
- [app/Http/Controllers/Web](../app/Http/Controllers/Web)

### Siapa target penggunanya

Target pengguna yang terlihat di codebase:
- owner
- admin
- supervisor
- cashier
- warehouse
- superadmin platform

Dasar temuan:
- role middleware pada [routes/api.php](../routes/api.php)
- role dan portal guard pada [app/Http/Middleware/WebAccessMiddleware.php](../app/Http/Middleware/WebAccessMiddleware.php)

### Modul utama yang terlihat

- POS dan transaksi penjualan
- refund dan void
- shift kasir
- inventory, stock adjustment, stock transfer, stock card
- products, categories, materials
- purchases, receiving, purchase returns
- suppliers dan customers
- finance summary, finance control, chart of accounts, bank accounts, lock periods
- analytics, reports, observability, anomalies
- production batches
- loyalty
- users dan branch management
- subscription, invoice, payment, audit logs
- onboarding dan portal web

### Alur utama bisnis yang terlihat

Secara garis besar alur bisnis yang tampak:
1. user onboarding membuat akun pemilik dan tenant,
2. tenant mengatur profil usaha dan metode inventory valuation,
3. sistem menjalankan transaksi POS, pembelian, receiving, stock movement, refund, dan shift,
4. backoffice menampilkan laporan operasional, inventory, analytics, dan finance,
5. subscription menentukan entitlement fitur tertentu,
6. invoice dan payment subscription memengaruhi lifecycle access untuk sebagian area.

## 2. Struktur Paket / Langganan Yang Sudah Ada

### Enum paket yang saat ini dipakai di codebase

Paket yang terlihat di codebase saat ini adalah:
- `S`
- `M`
- `P`

Label plan yang terlihat:
- `S` = `Starter`
- `M` = `Medium`
- `P` = `Pro`

Dasar file:
- [config/feature-gating.json](../config/feature-gating.json)
- [app/Support/FeatureGateConfig.php](../app/Support/FeatureGateConfig.php)
- [app/Services/SubscriptionService.php](../app/Services/SubscriptionService.php)
- [app/Models/Tenant.php](../app/Models/Tenant.php)

### Apakah paketnya S/M/L atau S/M/P atau yang lain

Implementasi teknis yang ditemukan adalah `S/M/P`, bukan `S/M/L`.

Inkonsistensi:
- kebutuhan bisnis terakhir yang ditulis user memakai `S/M/L`
- implementasi teknis aktif di codebase memakai `S/M/P`

### Di file mana definisinya muncul

| Lokasi | Temuan |
|---|---|
| [config/feature-gating.json](../config/feature-gating.json) | `planEnum`, `planLabels`, `featureFlags`, `planMapping` |
| [app/Support/FeatureGateConfig.php](../app/Support/FeatureGateConfig.php) | normalize plan, labels, entitlements |
| [app/Services/SubscriptionService.php](../app/Services/SubscriptionService.php) | urutan plan dan harga plan |
| [app/Models/Tenant.php](../app/Models/Tenant.php) | validasi setter/getter plan tenant |
| [app/Http/Controllers/Api/SubscriptionController.php](../app/Http/Controllers/Api/SubscriptionController.php) | validasi `plan` hanya `S,M,P` |

### Harga yang terlihat di codebase

Harga hardcoded yang ditemukan di service:
- `S` = `0.0`
- `M` = `199000.0`
- `P` = `399000.0`

Dasar file:
- [app/Services/SubscriptionService.php](../app/Services/SubscriptionService.php)

Catatan:
- ini harga teknis yang ada di kode saat ini,
- tidak ditemukan pricing config terpisah,
- tidak ditemukan pricing copy publik yang menjadi source of truth bisnis.

### Status subscription yang sudah ada

Status yang benar-benar terlihat dipakai atau direferensikan:
- `active`
- `trialing`
- `past_due`
- `suspended`
- `canceled`

Status yang tidak ditemukan jelas sebagai status runtime subscription:
- `pending_activation`
- `expired`

### Arti masing-masing status berdasarkan perilaku kode

| Status | Arti yang terlihat di kode |
|---|---|
| `active` | subscription aktif normal |
| `trialing` | tenant sedang trial; setelah trial berakhir bisa jadi `active` atau `past_due` |
| `past_due` | ada invoice overdue atau transisi sesudah trial berakhir dengan invoice belum beres |
| `suspended` | akses dibatasi setelah grace period habis atau suspend manual platform |
| `canceled` | dianggap status terblokir dalam beberapa check, tetapi alur transisi penuhnya belum terlihat jelas |

Dasar file:
- [app/Console/Commands/EnforceSubscriptionLifecycleCommand.php](../app/Console/Commands/EnforceSubscriptionLifecycleCommand.php)
- [app/Services/SubscriptionService.php](../app/Services/SubscriptionService.php)
- [app/Services/Platform/PlatformTenantService.php](../app/Services/Platform/PlatformTenantService.php)

### Apakah ada trial

Ya, secara teknis ada dukungan trial.

Bukti:
- kolom `trial_ends_at` di [database/migrations/2026_03_05_001200_harden_subscription_lifecycle_and_billing_tables.php](../database/migrations/2026_03_05_001200_harden_subscription_lifecycle_and_billing_tables.php)
- logic transisi `trialing` di [app/Console/Commands/EnforceSubscriptionLifecycleCommand.php](../app/Console/Commands/EnforceSubscriptionLifecycleCommand.php)

Namun:
- trial logic saat registrasi awal belum terlihat terhubung ke onboarding
- durasi trial default tidak ditemukan sebagai konfigurasi bisnis terpusat

### Apakah ada pending activation

Tidak ditemukan / belum terlihat di codebase.

Tidak terlihat:
- status `pending_activation`
- create flow subscription awal yang menunggu pembayaran sebelum aktivasi

### Apakah ada grace period

Ya.

Bukti:
- kolom `grace_ends_at`
- transisi `past_due -> suspended` setelah `grace_ends_at` terlewati

Dasar file:
- [database/migrations/2026_03_05_001200_harden_subscription_lifecycle_and_billing_tables.php](../database/migrations/2026_03_05_001200_harden_subscription_lifecycle_and_billing_tables.php)
- [app/Console/Commands/EnforceSubscriptionLifecycleCommand.php](../app/Console/Commands/EnforceSubscriptionLifecycleCommand.php)

### Apakah ada invoice dan payment flow

Ya, sudah ada.

Tabel terkait:
- `invoices`
- `invoice_items`
- `subscription_payments`
- `subscription_operations`

API terkait:
- `GET /api/v1/subscription/invoices`
- `GET /api/v1/subscription/invoices/{invoice}`
- `GET /api/v1/subscription/invoices/{invoice}/download`
- `POST /api/v1/subscription/invoices/{invoice}/mark-paid`

Dasar file:
- [database/migrations/2026_03_05_001200_harden_subscription_lifecycle_and_billing_tables.php](../database/migrations/2026_03_05_001200_harden_subscription_lifecycle_and_billing_tables.php)
- [app/Http/Controllers/Api/SubscriptionInvoiceController.php](../app/Http/Controllers/Api/SubscriptionInvoiceController.php)
- [routes/api.php](../routes/api.php)

## 3. Sumber Kebenaran Sistem Langganan

### Apakah plan aktif dibaca dari `tenants.plan` atau `subscriptions.plan`

Plan aktif saat ini dibaca dari `tenants.plan`.

Bukti:
- [app/Services/SubscriptionService.php](../app/Services/SubscriptionService.php) memiliki komentar: source of truth MVP adalah `tenant.plan`

### Apakah ada duplikasi sumber data plan

Ya.

Duplikasi yang terlihat:
- `tenants.plan`
- `subscriptions.plan`

Arti praktisnya:
- entitlement aktif dibaca dari tenant,
- lifecycle dan histori periodik tetap menyimpan plan di subscription row.

### Service atau class yang membaca current plan

| Komponen | Peran |
|---|---|
| [app/Services/SubscriptionService.php](../app/Services/SubscriptionService.php) | `currentPlan()` membaca plan aktif |
| [app/Support/FeatureGateConfig.php](../app/Support/FeatureGateConfig.php) | normalisasi dan evaluasi entitlement per plan |
| [app/Http/Controllers/Api/SubscriptionController.php](../app/Http/Controllers/Api/SubscriptionController.php) | memanggil current plan untuk API current subscription dan entitlements |
| [app/Support/MenuRegistry.php](../app/Support/MenuRegistry.php) | memfilter menu dari plan dan entitlement |

### Service atau class yang membaca current subscription status

| Komponen | Peran |
|---|---|
| [app/Services/SubscriptionService.php](../app/Services/SubscriptionService.php) | `currentSubscription()` dan `currentStatus()` |
| [app/Http/Middleware/SubscriptionStatusMiddleware.php](../app/Http/Middleware/SubscriptionStatusMiddleware.php) | validasi status yang diizinkan |
| [app/Http/Middleware/FeatureGateMiddleware.php](../app/Http/Middleware/FeatureGateMiddleware.php) | menampilkan status saat forbidden |
| [app/Http/Controllers/Api/SubscriptionController.php](../app/Http/Controllers/Api/SubscriptionController.php) | menampilkan status current subscription |

### Command, controller, atau service yang bisa mengubah plan atau status

| Komponen | Mengubah apa |
|---|---|
| [app/Services/SubscriptionService.php](../app/Services/SubscriptionService.php) | changePlan, pending plan apply, tenant.plan sync saat immediate change |
| [app/Http/Controllers/Api/SubscriptionController.php](../app/Http/Controllers/Api/SubscriptionController.php) | memicu update plan |
| [app/Console/Commands/EnforceSubscriptionLifecycleCommand.php](../app/Console/Commands/EnforceSubscriptionLifecycleCommand.php) | mengubah status trialing, active, past_due, suspended; menerapkan pending plan |
| [app/Http/Controllers/Api/SubscriptionInvoiceController.php](../app/Http/Controllers/Api/SubscriptionInvoiceController.php) | reaktivasi current subscription dari `past_due` atau `suspended` ke `active` |
| [app/Services/Platform/PlatformTenantService.php](../app/Services/Platform/PlatformTenantService.php) | suspend dan unsuspend tenant di level platform |

### Risiko drift data antara tenant dan subscription

Risiko drift data memang ada.

Contoh risiko:
- `subscriptions.plan` sudah berubah tetapi `tenants.plan` belum berubah
- status kembali `active` tetapi `tenants.plan` tetap plan downgraded cache
- tenant baru belum punya current subscription row tetapi current status fallback menjadi `active`

Tingkat risiko: tinggi untuk domain subscription, karena source of truth plan tidak tunggal.

## 4. Flow Registrasi / Onboarding

### User daftar dari mana

Onboarding web dimulai dari route:
- `/register`
- `/register/step-1`
- `/register/step-2`
- `/register/step-3`
- `/register/step-4`
- `/register/review`
- `/register/complete`

Dasar file:
- [routes/web.php](../routes/web.php)

### Tenant dibuat di mana

Tenant dibuat atau di-update di:
- [app/Services/OnboardingProvisioningService.php](../app/Services/OnboardingProvisioningService.php)

Method terkait:
- `completeCompanyProfileStep()`

### Owner dibuat di mana

Owner dibuat atau di-update di service yang sama:
- [app/Services/OnboardingProvisioningService.php](../app/Services/OnboardingProvisioningService.php)

### Alur onboarding aktual berdasarkan code

1. Step 1: user mengisi nama, email, phone, password, accept terms.
2. Step 2: user mengisi store_name, address, business_category, valuation_method, timezone.
3. Step 2 memicu provisioning tenant dan owner.
4. Step 3: user memilih valuation method.
5. Step 4: user mengisi produk awal atau skip.
6. Review: user melihat ringkasan data.
7. Complete: produk awal di-bootstrap bila tidak skip.
8. User diarahkan ke `/pos/dashboard`.

Dasar file:
- [app/Http/Controllers/Web/OnboardingController.php](../app/Http/Controllers/Web/OnboardingController.php)

### Apakah subscription awal dibuat otomatis

Tidak ditemukan / belum terlihat di codebase.

Gap yang terlihat:
- tidak ada pembuatan row `subscriptions` di onboarding controller
- tidak ada `RegistrationSubscriptionService`
- tidak ada field plan, trial, invoice, billing di flow onboarding web yang terbaca

Ini adalah gap fungsional penting.

### Kalau ya, service atau class mana

Tidak ditemukan / belum terlihat di codebase.

### Setelah onboarding selesai user diarahkan ke mana

User diarahkan ke:
- `/pos/dashboard`

Dasar file:
- [app/Http/Controllers/Web/OnboardingController.php](../app/Http/Controllers/Web/OnboardingController.php)

### Apakah ada pemilihan paket saat awal

Tidak ditemukan / belum terlihat di codebase.

### Apakah ada trial logic saat awal

Tidak ditemukan / belum terlihat di codebase.

Catatan:
- trial lifecycle ada di backend subscription,
- tetapi belum terlihat tersambung ke onboarding awal.

## 5. Feature Gating

### File config feature gate yang dipakai

Config utama feature gate:
- [config/feature-gating.json](../config/feature-gating.json)

Pembaca config:
- [app/Support/FeatureGateConfig.php](../app/Support/FeatureGateConfig.php)

### Daftar seluruh feature key yang ada

#### Global
- onboarding.flow
- search.global
- notification.list
- help.center

#### Dashboard
- dash.overview
- dash.insights
- dash.control

#### POS
- pos.checkout
- pos.customer
- pos.hold_bill
- pos.split_payment
- pos.refund
- pos.void

#### Shift
- shift.open
- shift.close
- shift.history
- shift.print_summary

#### History
- history.sales
- history.payments
- history.stock
- history.audit_log

#### Reports
- reports.summary
- reports.analytics_products
- reports.analytics_payments
- reports.purchases_view
- reports.inventory_view
- reports.inventory_stock_card
- reports.inventory_fifo_layers
- reports.download_pdf
- reports.export_csv

#### Finance
- finance.profit_loss
- finance.balance_sheet
- finance.cash_flow
- finance.lock_period

#### Product
- product.list
- product.category
- product.materials
- product.pricing
- product.valuation

#### Inventory
- inventory.stock
- inventory.adjustment
- inventory.transfer

#### Purchase
- purchase.order
- purchase.receive
- purchase.return
- purchase.stock_purchase

#### Supplier
- supplier.manage

#### Customer
- customer.manage

#### Loyalty
- loyalty.program

#### Settings
- settings.store_profile
- settings.printer_receipt
- settings.devices
- settings.payments
- settings.subscription_billing
- settings.tax
- settings.promo_default
- settings.staff_basic
- settings.branches_manage
- settings.coa
- settings.notifications
- settings.roles_permissions
- settings.multi_branch
- settings.inventory_valuation
- settings.observability
- settings.integrations

### Paket mana yang boleh mengakses masing-masing fitur

#### Paket S
Termasuk yang terlihat di mapping:
- onboarding.flow
- help.center
- dash.overview
- dash.insights
- pos.checkout
- pos.hold_bill
- pos.split_payment
- pos.refund
- pos.void
- shift.open
- shift.close
- shift.history
- history.sales
- history.payments
- history.stock
- reports.summary
- reports.analytics_products
- reports.inventory_view
- finance.profit_loss
- product.list
- product.category
- product.pricing
- inventory.stock
- inventory.adjustment
- inventory.transfer
- purchase.order
- purchase.receive
- purchase.stock_purchase
- supplier.manage
- settings.store_profile
- settings.printer_receipt
- settings.devices
- settings.payments
- settings.subscription_billing

#### Paket M
Paket M berisi semua entitlement yang juga aktif untuk M, termasuk tambahan:
- search.global
- notification.list
- pos.customer
- shift.print_summary
- reports.analytics_payments
- reports.purchases_view
- reports.download_pdf
- reports.export_csv
- product.materials
- purchase.return
- customer.manage
- loyalty.program
- settings.tax
- settings.promo_default
- settings.staff_basic
- settings.branches_manage
- settings.notifications

#### Paket P
Paket P berisi semua area premium tambahan:
- dash.control
- history.audit_log
- reports.inventory_stock_card
- reports.inventory_fifo_layers
- finance.balance_sheet
- finance.cash_flow
- finance.lock_period
- product.valuation
- settings.coa
- settings.roles_permissions
- settings.multi_branch
- settings.inventory_valuation
- settings.observability
- settings.integrations

Dasar file seluruh mapping:
- [config/feature-gating.json](../config/feature-gating.json)

### Middleware, policy, atau service yang mengecek fitur

| Komponen | Peran |
|---|---|
| [app/Support/FeatureGateConfig.php](../app/Support/FeatureGateConfig.php) | menentukan entitlement per plan |
| [app/Services/SubscriptionService.php](../app/Services/SubscriptionService.php) | `canAccessFeature()` |
| [app/Http/Middleware/FeatureGateMiddleware.php](../app/Http/Middleware/FeatureGateMiddleware.php) | guard API berdasarkan feature key |
| [app/Http/Middleware/WebAccessMiddleware.php](../app/Http/Middleware/WebAccessMiddleware.php) | guard halaman web berdasarkan portal, role, dan feature |
| [app/Support/MenuRegistry.php](../app/Support/MenuRegistry.php) | menyembunyikan menu berdasarkan feature access |

### Route atau area mana yang dilindungi

Contoh proteksi yang jelas terlihat:

| Area | Proteksi |
|---|---|
| Observability API | role + `feature_gate:settings.observability` |
| Valuation API | role + `feature_gate:settings.inventory_valuation` + `subscription_status:active,trialing` |
| Inventory valuation settings web | `web_access` dengan feature `settings.inventory_valuation` |
| Observability web | `web_access` dengan feature `settings.observability` |
| Inventory report web | `web_access` dengan `reports.inventory_view` |
| Stock card web | `web_access` dengan `reports.inventory_stock_card` |
| Integrations web | `web_access` dengan `settings.integrations` |

Namun ada area yang terlihat hanya memakai:
- role middleware,
- atau feature umum seperti `dash.overview`,
- bukan feature key domain yang paling spesifik.

Catatan penting untuk resep, material, dan produksi:
- `product.materials` memang ada di [config/feature-gating.json](../config/feature-gating.json) dan secara config hanya untuk `M` dan `P`
- tetapi route API `materials` di [routes/api.php](../routes/api.php) yang terlihat saat ini hanya dijaga role `admin,owner,superadmin`, belum terlihat memakai `feature_gate:product.materials`
- route web `materials` di [routes/web.php](../routes/web.php) juga terlihat memakai gate umum `dash.overview`, bukan gate spesifik material
- fitur produksi batch di route API dan web juga belum terlihat memiliki feature key khusus seperti `production.batch` atau `recipe.bom`
- akibatnya, secara runtime saat ini ada kemungkinan fitur material, resep/BOM, dan produksi belum benar-benar dibatasi per paket seketat mapping bisnis yang diharapkan

### Apakah pembatasan berdasarkan plan saja atau plan + subscription status

Jawaban: keduanya.

Yang terlihat:
- plan gating melalui entitlement config
- status gating melalui subscription status middleware

Namun coverage status gating belum terlihat merata ke semua area.

## 6. Daftar Fitur Produk Nyata

### Kasir / Penjualan

| Fitur | Deskripsi singkat | File terkait | Level indikatif |
|---|---|---|---|
| Checkout POS | proses transaksi kasir utama | [routes/api.php](../routes/api.php), [routes/web.php](../routes/web.php) | basic |
| Split payment | pembayaran multi metode | [config/feature-gating.json](../config/feature-gating.json) | basic |
| Hold bill | simpan transaksi sementara | [config/feature-gating.json](../config/feature-gating.json) | basic |
| Customer on POS | pemilihan customer saat transaksi | [config/feature-gating.json](../config/feature-gating.json) | menengah |
| Sales detail | detail per transaksi | [routes/api.php](../routes/api.php), [routes/web.php](../routes/web.php) | basic |

### Pembelian

| Fitur | Deskripsi singkat | File terkait | Level indikatif |
|---|---|---|---|
| Purchase order | daftar dan create pembelian | [routes/api.php](../routes/api.php) | basic |
| Receiving | penerimaan barang dari pembelian | [routes/api.php](../routes/api.php), [routes/web.php](../routes/web.php) | basic |
| Purchase return | retur pembelian | [routes/api.php](../routes/api.php), [config/feature-gating.json](../config/feature-gating.json) | menengah |
| Stock purchase action | aksi pembelian yang memengaruhi stok | [config/feature-gating.json](../config/feature-gating.json) | basic |

### Stok / Inventory

| Fitur | Deskripsi singkat | File terkait | Level indikatif |
|---|---|---|---|
| Inventory summary | ringkasan SKU, stok rendah, nilai stok, movement | [app/Http/Controllers/Api/InventoryReportController.php](../app/Http/Controllers/Api/InventoryReportController.php) | basic |
| Stock alerts | deteksi stok rendah/habis | [routes/api.php](../routes/api.php), [routes/web.php](../routes/web.php) | basic |
| Stock card | ledger stok per produk | [routes/api.php](../routes/api.php), [routes/web.php](../routes/web.php) | premium |
| FIFO layers report | detail valuation layer FIFO | [config/feature-gating.json](../config/feature-gating.json) | premium |
| Stock adjustment | draft, approve, post, cancel adjustment | [routes/api.php](../routes/api.php) | basic |
| Stock transfer | approve, ship, receive, close transfer | [routes/api.php](../routes/api.php) | basic |

### Pelanggan

| Fitur | Deskripsi singkat | File terkait | Level indikatif |
|---|---|---|---|
| Customer manage | kelola data customer | [routes/api.php](../routes/api.php), [config/feature-gating.json](../config/feature-gating.json) | menengah |

### Supplier

| Fitur | Deskripsi singkat | File terkait | Level indikatif |
|---|---|---|---|
| Supplier manage | kelola supplier | [routes/api.php](../routes/api.php), [config/feature-gating.json](../config/feature-gating.json) | basic |

### Akuntansi / Laporan Keuangan

| Fitur | Deskripsi singkat | File terkait | Level indikatif |
|---|---|---|---|
| Finance summary | revenue, expenses, profit | [app/Http/Controllers/Api/FinanceReportController.php](../app/Http/Controllers/Api/FinanceReportController.php) | basic |
| Balance sheet | assets, liabilities, equity | [app/Http/Controllers/Api/FinanceReportController.php](../app/Http/Controllers/Api/FinanceReportController.php) | premium |
| Cash flow | fitur cash flow di entitlement | [config/feature-gating.json](../config/feature-gating.json) | premium |
| Chart of accounts | pengelolaan akun akuntansi | [routes/api.php](../routes/api.php), [routes/web.php](../routes/web.php) | premium |
| Lock periods | penguncian periode finance | [routes/api.php](../routes/api.php), [config/feature-gating.json](../config/feature-gating.json) | premium |
| Finance control daily | KPI harian dan mingguan kontrol finance | [app/Http/Controllers/Api/FinanceControlDashboardController.php](../app/Http/Controllers/Api/FinanceControlDashboardController.php) | menengah sampai premium |

### Shift / Kas

| Fitur | Deskripsi singkat | File terkait | Level indikatif |
|---|---|---|---|
| Open shift | buka shift | [routes/api.php](../routes/api.php) | basic |
| Close shift | tutup shift | [routes/api.php](../routes/api.php) | basic |
| Shift summary | summary dan close summary | [routes/api.php](../routes/api.php), [routes/web.php](../routes/web.php) | basic |
| Print shift summary | cetak summary shift | [config/feature-gating.json](../config/feature-gating.json) | menengah |

### Multi cabang / Lokasi

| Fitur | Deskripsi singkat | File terkait | Level indikatif |
|---|---|---|---|
| Branches | list, show, store, update branch | [routes/api.php](../routes/api.php), [routes/web.php](../routes/web.php) | menengah sampai premium |
| Multi branch full | entitlement multi branch penuh | [config/feature-gating.json](../config/feature-gating.json) | premium |

### User / Role / Permission

| Fitur | Deskripsi singkat | File terkait | Level indikatif |
|---|---|---|---|
| User management | list, create, update user tenant | [routes/api.php](../routes/api.php), [routes/web.php](../routes/web.php) | menengah |
| Staff basic | pengaturan staff dasar | [config/feature-gating.json](../config/feature-gating.json) | menengah |
| Roles permissions | role dan permission tingkat lanjut | [config/feature-gating.json](../config/feature-gating.json) | premium |

### Audit / Monitoring / Anomaly

| Fitur | Deskripsi singkat | File terkait | Level indikatif |
|---|---|---|---|
| Observability health | queue, lag, anomaly metrics, detector metrics | [app/Http/Controllers/Api/ObservabilityController.php](../app/Http/Controllers/Api/ObservabilityController.php) | premium |
| Anomaly list | list anomaly by severity, type, range | [app/Http/Controllers/Api/AnomalyController.php](../app/Http/Controllers/Api/AnomalyController.php) | premium |
| Resolve anomaly | mark anomaly resolved + audit log | [app/Http/Controllers/Api/AnomalyController.php](../app/Http/Controllers/Api/AnomalyController.php) | premium |
| Refund legacy audit | audit mismatch refund legacy | [app/Http/Controllers/Api/RefundLegacyAuditController.php](../app/Http/Controllers/Api/RefundLegacyAuditController.php) | premium |

### Promosi / Voucher / Loyalty

| Fitur | Deskripsi singkat | File terkait | Level indikatif |
|---|---|---|---|
| Loyalty program | modul loyalty | [routes/api.php](../routes/api.php), [routes/web.php](../routes/web.php) | menengah |
| Tax settings | pengaturan pajak | [config/feature-gating.json](../config/feature-gating.json) | menengah |
| Promo default | pengaturan promo | [config/feature-gating.json](../config/feature-gating.json) | menengah |

### Register / Payment Method / Refund

| Fitur | Deskripsi singkat | File terkait | Level indikatif |
|---|---|---|---|
| Payment method page | halaman metode pembayaran POS | [routes/web.php](../routes/web.php) | basic |
| Refund | refund transaksi | [routes/api.php](../routes/api.php) | basic |
| Void | void transaksi | [routes/api.php](../routes/api.php) | basic |

### Produksi / Materials / Batch

| Fitur | Deskripsi singkat | File terkait | Level indikatif |
|---|---|---|---|
| Materials | daftar dan kelola material | [routes/api.php](../routes/api.php), [config/feature-gating.json](../config/feature-gating.json) | menengah secara config, tetapi enforcement runtime belum terlihat spesifik |
| Recipe / BOM pada produk | definisi komponen bahan pada produk melalui `bom_items` dan `production_mode` | [app/Http/Controllers/Api/ProductController.php](../app/Http/Controllers/Api/ProductController.php) | premium secara domain, tetapi belum terlihat punya feature key khusus |
| Production batches | create, update, post, cancel batch produksi | [routes/api.php](../routes/api.php), [routes/web.php](../routes/web.php), [app/Http/Controllers/Api/ProductionBatchController.php](../app/Http/Controllers/Api/ProductionBatchController.php) | premium secara domain, tetapi enforcement runtime belum terlihat spesifik |
| Problematic batch export | export batch bermasalah | [routes/api.php](../routes/api.php) | premium |

### Subscription / Billing

| Fitur | Deskripsi singkat | File terkait | Level indikatif |
|---|---|---|---|
| Subscription current | plan aktif, status, pending change | [app/Http/Controllers/Api/SubscriptionController.php](../app/Http/Controllers/Api/SubscriptionController.php) | basic billing |
| Entitlements | daftar fitur allowed/blocked | [app/Http/Controllers/Api/SubscriptionController.php](../app/Http/Controllers/Api/SubscriptionController.php) | basic billing |
| Impact preview | preview upgrade/downgrade dan proration | [app/Http/Controllers/Api/SubscriptionController.php](../app/Http/Controllers/Api/SubscriptionController.php) | menengah billing |
| Subscription audit logs | histori perubahan plan | [app/Http/Controllers/Api/SubscriptionController.php](../app/Http/Controllers/Api/SubscriptionController.php) | menengah billing |
| Invoices | list, detail, download invoice | [app/Http/Controllers/Api/SubscriptionInvoiceController.php](../app/Http/Controllers/Api/SubscriptionInvoiceController.php) | basic billing |
| Mark paid | pencatatan pembayaran invoice | [app/Http/Controllers/Api/SubscriptionInvoiceController.php](../app/Http/Controllers/Api/SubscriptionInvoiceController.php) | menengah billing |

### Pengaturan

| Fitur | Deskripsi singkat | File terkait | Level indikatif |
|---|---|---|---|
| Store profile | pengaturan profil toko | [config/feature-gating.json](../config/feature-gating.json) | basic |
| Printer receipt | pengaturan printer struk | [config/feature-gating.json](../config/feature-gating.json) | basic |
| Devices | pengaturan device | [config/feature-gating.json](../config/feature-gating.json) | basic |
| Payments settings | pengaturan payment | [config/feature-gating.json](../config/feature-gating.json) | basic |
| Subscription billing settings | area billing langganan | [config/feature-gating.json](../config/feature-gating.json) | basic |
| Notifications settings | pengaturan notifikasi | [config/feature-gating.json](../config/feature-gating.json) | menengah |
| Inventory valuation settings | report, rebuild, reconciliation valuation | [routes/api.php](../routes/api.php) | premium |
| Integrations | integrasi | [config/feature-gating.json](../config/feature-gating.json) | premium |

## 7. Pemetaan Fitur Ke Paket

Bagian ini berdasarkan config yang ada sekarang, bukan usulan bisnis baru.

| Fitur | Tersedia di paket mana | Dasar penentuan |
|---|---|---|
| onboarding.flow | S, M, P | [config/feature-gating.json](../config/feature-gating.json) |
| search.global | M, P | [config/feature-gating.json](../config/feature-gating.json) |
| notification.list | M, P | [config/feature-gating.json](../config/feature-gating.json) |
| help.center | S, M, P | [config/feature-gating.json](../config/feature-gating.json) |
| dash.overview | S, M, P | [config/feature-gating.json](../config/feature-gating.json) |
| dash.insights | S, M, P | [config/feature-gating.json](../config/feature-gating.json) |
| dash.control | P | [config/feature-gating.json](../config/feature-gating.json) |
| pos.checkout | S, M, P | [config/feature-gating.json](../config/feature-gating.json) |
| pos.customer | M, P | [config/feature-gating.json](../config/feature-gating.json) |
| pos.hold_bill | S, M, P | [config/feature-gating.json](../config/feature-gating.json) |
| pos.split_payment | S, M, P | [config/feature-gating.json](../config/feature-gating.json) |
| pos.refund | S, M, P | [config/feature-gating.json](../config/feature-gating.json) |
| pos.void | S, M, P | [config/feature-gating.json](../config/feature-gating.json) |
| shift.open | S, M, P | [config/feature-gating.json](../config/feature-gating.json) |
| shift.close | S, M, P | [config/feature-gating.json](../config/feature-gating.json) |
| shift.history | S, M, P | [config/feature-gating.json](../config/feature-gating.json) |
| shift.print_summary | M, P | [config/feature-gating.json](../config/feature-gating.json) |
| history.sales | S, M, P | [config/feature-gating.json](../config/feature-gating.json) |
| history.payments | S, M, P | [config/feature-gating.json](../config/feature-gating.json) |
| history.stock | S, M, P | [config/feature-gating.json](../config/feature-gating.json) |
| history.audit_log | P | [config/feature-gating.json](../config/feature-gating.json) |
| reports.summary | S, M, P | [config/feature-gating.json](../config/feature-gating.json) |
| reports.analytics_products | S, M, P | [config/feature-gating.json](../config/feature-gating.json) |
| reports.analytics_payments | M, P | [config/feature-gating.json](../config/feature-gating.json) |
| reports.purchases_view | M, P | [config/feature-gating.json](../config/feature-gating.json) |
| reports.inventory_view | S, M, P | [config/feature-gating.json](../config/feature-gating.json) |
| reports.inventory_stock_card | P | [config/feature-gating.json](../config/feature-gating.json) |
| reports.inventory_fifo_layers | P | [config/feature-gating.json](../config/feature-gating.json) |
| reports.download_pdf | M, P | [config/feature-gating.json](../config/feature-gating.json) |
| reports.export_csv | M, P | [config/feature-gating.json](../config/feature-gating.json) |
| finance.profit_loss | S, M, P | [config/feature-gating.json](../config/feature-gating.json) |
| finance.balance_sheet | P | [config/feature-gating.json](../config/feature-gating.json) |
| finance.cash_flow | P | [config/feature-gating.json](../config/feature-gating.json) |
| finance.lock_period | P | [config/feature-gating.json](../config/feature-gating.json) |
| product.list | S, M, P | [config/feature-gating.json](../config/feature-gating.json) |
| product.category | S, M, P | [config/feature-gating.json](../config/feature-gating.json) |
| product.materials | M, P | [config/feature-gating.json](../config/feature-gating.json) |
| recipe / BOM | tidak ditemukan key gate khusus | [app/Http/Controllers/Api/ProductController.php](../app/Http/Controllers/Api/ProductController.php) |
| production batches | tidak ditemukan key gate khusus | [routes/api.php](../routes/api.php), [app/Http/Controllers/Api/ProductionBatchController.php](../app/Http/Controllers/Api/ProductionBatchController.php) |
| product.pricing | S, M, P | [config/feature-gating.json](../config/feature-gating.json) |
| product.valuation | P | [config/feature-gating.json](../config/feature-gating.json) |
| inventory.stock | S, M, P | [config/feature-gating.json](../config/feature-gating.json) |
| inventory.adjustment | S, M, P | [config/feature-gating.json](../config/feature-gating.json) |
| inventory.transfer | S, M, P | [config/feature-gating.json](../config/feature-gating.json) |
| purchase.order | S, M, P | [config/feature-gating.json](../config/feature-gating.json) |
| purchase.receive | S, M, P | [config/feature-gating.json](../config/feature-gating.json) |
| purchase.return | M, P | [config/feature-gating.json](../config/feature-gating.json) |
| purchase.stock_purchase | S, M, P | [config/feature-gating.json](../config/feature-gating.json) |
| supplier.manage | S, M, P | [config/feature-gating.json](../config/feature-gating.json) |
| customer.manage | M, P | [config/feature-gating.json](../config/feature-gating.json) |
| loyalty.program | M, P | [config/feature-gating.json](../config/feature-gating.json) |
| settings.store_profile | S, M, P | [config/feature-gating.json](../config/feature-gating.json) |
| settings.printer_receipt | S, M, P | [config/feature-gating.json](../config/feature-gating.json) |
| settings.devices | S, M, P | [config/feature-gating.json](../config/feature-gating.json) |
| settings.payments | S, M, P | [config/feature-gating.json](../config/feature-gating.json) |
| settings.subscription_billing | S, M, P | [config/feature-gating.json](../config/feature-gating.json) |
| settings.tax | M, P | [config/feature-gating.json](../config/feature-gating.json) |
| settings.promo_default | M, P | [config/feature-gating.json](../config/feature-gating.json) |
| settings.staff_basic | M, P | [config/feature-gating.json](../config/feature-gating.json) |
| settings.branches_manage | M, P | [config/feature-gating.json](../config/feature-gating.json) |
| settings.coa | P | [config/feature-gating.json](../config/feature-gating.json) |
| settings.notifications | M, P | [config/feature-gating.json](../config/feature-gating.json) |
| settings.roles_permissions | P | [config/feature-gating.json](../config/feature-gating.json) |
| settings.multi_branch | P | [config/feature-gating.json](../config/feature-gating.json) |
| settings.inventory_valuation | P | [config/feature-gating.json](../config/feature-gating.json) |
| settings.observability | P | [config/feature-gating.json](../config/feature-gating.json) |
| settings.integrations | P | [config/feature-gating.json](../config/feature-gating.json) |

Kalau ada fitur yang belum jelas mapping-nya:
- beberapa route memakai gate umum seperti `dash.overview`
- beberapa route hanya role-based tanpa feature gate domain spesifik
- karena itu, mapping config belum selalu sama dengan coverage proteksi route runtime.

## 8. Daftar Laporan Yang Sudah Ada

### Laporan dasar

| Nama laporan | Isinya | Pertanyaan bisnis yang dijawab | Cocok teknis saat ini | Dasar file |
|---|---|---|---|---|
| Finance summary | net profit, revenue, expenses, tax, breakdown | berapa laba rugi dalam periode tertentu | S, M, P | [app/Http/Controllers/Api/FinanceReportController.php](../app/Http/Controllers/Api/FinanceReportController.php) |
| Sales history summary | sales summary, payment mix, top products, average ticket | bagaimana performa penjualan periode tertentu | S, M, P | [app/Http/Controllers/Api/SalesHistoryController.php](../app/Http/Controllers/Api/SalesHistoryController.php) |
| Inventory summary | jumlah SKU, stok rendah, stok habis, nilai stok, movement | bagaimana kondisi stok dan nilai persediaan | S, M, P | [app/Http/Controllers/Api/InventoryReportController.php](../app/Http/Controllers/Api/InventoryReportController.php) |
| Analytics summary | revenue today/yesterday, orders, profit, trend, top products, payment mix | bagaimana performa penjualan harian dan tren ringkas | S, M, P | [app/Http/Controllers/Api/AnalyticsController.php](../app/Http/Controllers/Api/AnalyticsController.php) |
| Shift summary | opening, summary, close summary, closed history | bagaimana performa shift kasir | S, M, P, dengan print summary M/P | [routes/api.php](../routes/api.php) |

### Laporan detail

| Nama laporan | Isinya | Pertanyaan bisnis yang dijawab | Cocok teknis saat ini | Dasar file |
|---|---|---|---|---|
| Sales history detail | gross sales, discounts, refunds, tax, payments, cashier, branch | transaksi mana yang menyumbang omzet dan refund | S, M, P | [app/Http/Controllers/Api/SalesHistoryController.php](../app/Http/Controllers/Api/SalesHistoryController.php) |
| Inventory movement detail | movement by type, recent movements, audit trail | dari mana stok masuk dan keluar | S, M, P | [app/Http/Controllers/Api/InventoryReportController.php](../app/Http/Controllers/Api/InventoryReportController.php) |
| Purchase report | view pembelian, receiving, retur | bagaimana aktivitas pembelian selama periode | M, P | [config/feature-gating.json](../config/feature-gating.json) |
| Inventory stock card | ledger stok per produk | bagaimana histori stok item tertentu | P | [config/feature-gating.json](../config/feature-gating.json), [routes/web.php](../routes/web.php) |
| Finance control daily | KPI harian dan mingguan finance control | apakah kontrol kas dan finance harian sehat | belum pasti paket, role-based saat ini | [app/Http/Controllers/Api/FinanceControlDashboardController.php](../app/Http/Controllers/Api/FinanceControlDashboardController.php) |

### Laporan premium / lanjutan

| Nama laporan | Isinya | Pertanyaan bisnis yang dijawab | Cocok teknis saat ini | Dasar file |
|---|---|---|---|---|
| Balance sheet | assets, liabilities, equity | bagaimana posisi neraca bisnis | P | [app/Http/Controllers/Api/FinanceReportController.php](../app/Http/Controllers/Api/FinanceReportController.php) |
| Cash flow | arus kas | bagaimana pergerakan kas bisnis | P | [config/feature-gating.json](../config/feature-gating.json) |
| Lock period finance | governance periode tutup buku | apakah periode tertentu masih bisa diubah | P | [config/feature-gating.json](../config/feature-gating.json) |
| FIFO layers report | layer FIFO dan valuation detail | bagaimana lapisan biaya stok terbentuk | P | [config/feature-gating.json](../config/feature-gating.json) |
| Valuation reports | daftar file report, export async, status, retry | bagaimana perbandingan valuasi dan export lanjutannya | P | [app/Http/Controllers/Api/ValuationReportController.php](../app/Http/Controllers/Api/ValuationReportController.php) |
| Valuation reconciliation | rekonsiliasi valuation | apakah valuation konsisten dengan ledger dan state | P | [routes/api.php](../routes/api.php) |
| Valuation audit logs | audit log valuation action | siapa melakukan export atau rebuild valuation | P | [routes/api.php](../routes/api.php) |
| Observability health | queue depth, lag, anomalies, detector metrics | apakah sistem sehat secara operasional | P | [app/Http/Controllers/Api/ObservabilityController.php](../app/Http/Controllers/Api/ObservabilityController.php) |
| Anomaly list | anomaly by type, severity, resolved | masalah operasional apa yang sedang terjadi | premium secara posisi teknis | [app/Http/Controllers/Api/AnomalyController.php](../app/Http/Controllers/Api/AnomalyController.php) |
| Refund legacy audit | audit mismatch refund legacy dan remediation | refund lama mana yang bermasalah dan bisa diperbaiki | premium secara posisi teknis | [app/Http/Controllers/Api/RefundLegacyAuditController.php](../app/Http/Controllers/Api/RefundLegacyAuditController.php) |

Catatan ketidakpastian:
- beberapa laporan premium terlihat jelas dari gate config,
- beberapa dashboard KPI lanjutan belum memiliki feature key plan yang spesifik di route,
- sehingga mapping paket final untuk semua laporan belum selalu eksplisit di runtime route.

## 9. Status Area Yang Cocok Untuk Paket S / M / L

Bagian ini dibedakan antara kondisi teknis saat ini dan pembacaan bisnis kasar dari struktur yang sudah ada.

### Kondisi teknis saat ini di codebase

| Area | Posisi teknis saat ini |
|---|---|
| Paket S | POS inti, shift dasar, inventory basic, purchase basic, supplier, product basic, reports dasar, finance profit_loss, billing page dasar |
| Paket M | customer, loyalty, search global, notifications, purchase return, materials, tax/promo, export report, PDF, CSV, staff basic, branch manage |
| Paket P | observability, valuation, audit log, stock card premium, FIFO layer report, balance sheet, cash flow, lock period, roles permissions, integrations, multi branch penuh |

### Usulan bisnis yang paling masuk akal

Belum diisi di dokumen ini, karena permintaan user untuk dokumen ini adalah fokus pada kondisi yang SUDAH ADA dan tidak memberi saran terlebih dahulu.

Catatan:
- klasifikasi bisnis final S/M/L belum bisa dianggap final dari codebase,
- karena codebase masih memakai S/M/P.

## 10. Inkonsistensi / Risiko / Bug Potensial

### Mismatch enum paket

Inkonsistensi ditemukan:
- bisnis terakhir menyebut `S/M/L`
- codebase memakai `S/M/P`

File bukti:
- [config/feature-gating.json](../config/feature-gating.json)
- [app/Services/SubscriptionService.php](../app/Services/SubscriptionService.php)
- [app/Http/Controllers/Api/SubscriptionController.php](../app/Http/Controllers/Api/SubscriptionController.php)

### Mismatch source of truth

Ada mismatch source of truth plan:
- entitlement aktif dari `tenants.plan`
- lifecycle row tetap menyimpan `subscriptions.plan`

Risiko:
- drift plan antar tabel

### Subscription bisa aktif tanpa row current atau tidak

Berpotensi iya.

Alasannya:
- `currentStatus()` fallback ke `active` bila current subscription tidak ada
- onboarding belum terlihat membuat row subscription awal

Dasar file:
- [app/Services/SubscriptionService.php](../app/Services/SubscriptionService.php)
- [app/Http/Controllers/Web/OnboardingController.php](../app/Http/Controllers/Web/OnboardingController.php)

### Pembayaran gagal tapi feature masih aktif atau tidak

Belum pasti sepenuhnya aman.

Yang terlihat:
- `past_due` ada sebagai status
- beberapa endpoint sensitif memakai `subscription_status:active,trialing`
- tetapi `canAccessFeature()` hanya langsung memblok `suspended` dan `canceled`

Artinya:
- jika route tidak memakai status middleware tambahan,
- tenant `past_due` masih mungkin mengakses area tertentu sesuai feature gate plan.

### Suspended tapi `tenant.plan` tidak sinkron atau tidak

Ada potensi sinkronisasi bermasalah.

Yang terlihat:
- saat lifecycle command mensuspend tenant, `tenants.plan` di-set ke `S`
- saat invoice dibayar dan subscription diaktifkan kembali, pemulihan `tenants.plan` ke plan premium tidak terlihat jelas

Dasar file:
- [app/Console/Commands/EnforceSubscriptionLifecycleCommand.php](../app/Console/Commands/EnforceSubscriptionLifecycleCommand.php)
- [app/Http/Controllers/Api/SubscriptionInvoiceController.php](../app/Http/Controllers/Api/SubscriptionInvoiceController.php)

### Status fallback yang terlalu permisif

Ada.

Contoh:
- `currentStatus()` default `active`

Implikasi:
- tenant tanpa current subscription row bisa diperlakukan aktif.

### Hardcoded price

Ada.

Harga plan ditemukan hardcoded di:
- [app/Services/SubscriptionService.php](../app/Services/SubscriptionService.php)

### Route yang belum terjaga middleware

Belum pasti semua route terjaga dengan feature gate domain yang paling tepat.

Contoh pola yang terlihat:
- beberapa route premium dijaga ketat oleh `feature_gate` + `subscription_status`
- beberapa route web memakai gate umum `dash.overview`
- beberapa route hanya role-based tanpa feature key domain spesifik

Implikasi:
- coverage entitlement runtime berpotensi tidak seragam.

Contoh yang cukup jelas:
- materials sudah punya mapping config `product.materials` untuk `M/P`, tetapi route API dan web yang terbaca belum memakai gate spesifik itu
- recipe/BOM pada produk sudah ada di [app/Http/Controllers/Api/ProductController.php](../app/Http/Controllers/Api/ProductController.php), tetapi belum terlihat punya feature key sendiri
- production batches sudah ada penuh di [app/Http/Controllers/Api/ProductionBatchController.php](../app/Http/Controllers/Api/ProductionBatchController.php), tetapi route yang terbaca masih dominan role-based atau gate umum, bukan entitlement paket spesifik

### Bug atau gap lain terkait paket, langganan, dan gating

- onboarding belum terlihat membuat subscription awal
- pending activation belum ada
- pricing config belum dipisah dari logic service
- dedicated `Subscription` Eloquent model tidak terlihat
- arti resmi setiap status belum terlihat sebagai enum/domain object terpusat

## 11. File Dan Class Penting

| File / class | Fungsi |
|---|---|
| [app/Services/SubscriptionService.php](../app/Services/SubscriptionService.php) | service inti subscription untuk current plan, current status, preview plan change, plan change, proration, dan apply pending plan |
| [app/Http/Controllers/Api/SubscriptionController.php](../app/Http/Controllers/Api/SubscriptionController.php) | API current subscription, entitlements, impact preview, audit logs, dan update plan |
| [app/Http/Controllers/Api/SubscriptionInvoiceController.php](../app/Http/Controllers/Api/SubscriptionInvoiceController.php) | API list invoice, detail invoice, download invoice, dan mark paid |
| [app/Support/FeatureGateConfig.php](../app/Support/FeatureGateConfig.php) | pembaca config feature gate, normalisasi plan, cek entitlement |
| [app/Http/Middleware/FeatureGateMiddleware.php](../app/Http/Middleware/FeatureGateMiddleware.php) | middleware API untuk memblok akses fitur |
| [app/Http/Middleware/SubscriptionStatusMiddleware.php](../app/Http/Middleware/SubscriptionStatusMiddleware.php) | middleware API untuk memblok operasi berdasarkan status subscription |
| [app/Http/Middleware/WebAccessMiddleware.php](../app/Http/Middleware/WebAccessMiddleware.php) | middleware halaman web untuk validasi portal, role, dan feature |
| [app/Http/Controllers/Web/OnboardingController.php](../app/Http/Controllers/Web/OnboardingController.php) | flow onboarding step-by-step hingga redirect dashboard |
| [app/Services/OnboardingProvisioningService.php](../app/Services/OnboardingProvisioningService.php) | pembuatan atau update tenant dan owner di onboarding |
| [app/Services/OnboardingFlowStateService.php](../app/Services/OnboardingFlowStateService.php) | penyimpanan session state onboarding per step |
| [app/Console/Commands/EnforceSubscriptionLifecycleCommand.php](../app/Console/Commands/EnforceSubscriptionLifecycleCommand.php) | command lifecycle subscription berdasarkan trial, overdue, grace, dan pending change |
| [config/feature-gating.json](../config/feature-gating.json) | file utama mapping feature ke paket |
| [database/migrations/2026_02_24_045757_create_subscriptions_table.php](../database/migrations/2026_02_24_045757_create_subscriptions_table.php) | migration subscriptions dasar |
| [database/migrations/2026_03_05_001200_harden_subscription_lifecycle_and_billing_tables.php](../database/migrations/2026_03_05_001200_harden_subscription_lifecycle_and_billing_tables.php) | migration lifecycle, invoice, payment, operation log |
| [database/migrations/2026_03_05_001300_add_subscription_plan_change_schedule_columns.php](../database/migrations/2026_03_05_001300_add_subscription_plan_change_schedule_columns.php) | migration pending plan change |
| [database/migrations/2026_03_05_000100_add_plan_to_tenants_and_create_subscription_audit_logs_table.php](../database/migrations/2026_03_05_000100_add_plan_to_tenants_and_create_subscription_audit_logs_table.php) | migration tenant plan dan subscription audit log |
| [app/Models/Tenant.php](../app/Models/Tenant.php) | model tenant yang menormalisasi kolom `plan` |
| RegistrationSubscriptionService | tidak ditemukan / belum terlihat di codebase |

## 12. Kesimpulan Final

Sistem paket dan langganan saat ini sudah memiliki fondasi yang cukup lengkap untuk MVP billing dan entitlement:
- ada tabel subscription,
- ada invoice dan payment,
- ada audit logs,
- ada pending plan scheduling,
- ada trial dan grace period,
- ada feature gating terpusat.

Kekuatan struktur yang sudah ada:
- feature entitlement disusun di satu config utama,
- service subscription sudah punya preview, proration, dan scheduled change,
- billing invoice dan payment flow sudah ada,
- lifecycle command sudah mengelola trial, overdue, dan suspend,
- area premium sudah cukup jelas di config.

Celah terbesar saat ini:
- source of truth plan masih ganda antara tenant dan subscription,
- onboarding belum terlihat membuat subscription awal otomatis,
- status fallback terlalu permisif,
- enum bisnis dan enum teknis belum sejajar,
- coverage route terhadap feature gate belum tampak seragam sepenuhnya.

Yang masih belum diketahui dengan pasti:
- apakah ada alur pembuatan subscription awal di tempat lain yang belum terbaca,
- apakah ada job atau listener lain yang menormalkan tenant.plan setelah reactivation,
- apakah seluruh feature key di config sudah benar-benar digunakan konsisten di UI dan backend,
- apakah ada dokumen bisnis resmi yang mendefinisikan paket final dan arti status runtime.

---

## A. Versi Sederhana Untuk Non-Teknis

Sistem langganan Anda sebenarnya sudah punya banyak bagian penting:
- sudah ada paket,
- sudah ada tagihan,
- sudah ada pembayaran,
- sudah ada masa trial,
- sudah ada status telat bayar dan suspend,
- sudah ada penguncian fitur sesuai paket.

Masalah utamanya adalah sambungan antar bagiannya belum sepenuhnya rapi.

Saat ini toko dan pemilik toko bisa dibuat saat pendaftaran, tetapi langganan awalnya belum terlihat otomatis dibuat. Selain itu, paket aktif disimpan di dua tempat berbeda. Ini bisa membuat sistem kadang membaca paket yang tidak sama antara status langganan dan hak fitur.

Jadi secara sederhana:
- pondasi subscription sudah bagus,
- tetapi alur registrasi awal dan sinkronisasi paket aktif masih punya celah.

## B. Data Yang Masih Perlu Dikirim Ke ChatGPT

Agar analisis paket bisa 100 persen akurat di model lain, file atau potongan kode yang masih perlu dikirim adalah:

- [config/feature-gating.json](../config/feature-gating.json)
- [app/Services/SubscriptionService.php](../app/Services/SubscriptionService.php)
- [app/Http/Controllers/Api/SubscriptionController.php](../app/Http/Controllers/Api/SubscriptionController.php)
- [app/Http/Controllers/Api/SubscriptionInvoiceController.php](../app/Http/Controllers/Api/SubscriptionInvoiceController.php)
- [app/Console/Commands/EnforceSubscriptionLifecycleCommand.php](../app/Console/Commands/EnforceSubscriptionLifecycleCommand.php)
- [app/Http/Controllers/Web/OnboardingController.php](../app/Http/Controllers/Web/OnboardingController.php)
- [app/Services/OnboardingProvisioningService.php](../app/Services/OnboardingProvisioningService.php)
- [app/Services/OnboardingFlowStateService.php](../app/Services/OnboardingFlowStateService.php)
- migration subscriptions dan billing:
  - [database/migrations/2026_02_24_045757_create_subscriptions_table.php](../database/migrations/2026_02_24_045757_create_subscriptions_table.php)
  - [database/migrations/2026_03_05_001200_harden_subscription_lifecycle_and_billing_tables.php](../database/migrations/2026_03_05_001200_harden_subscription_lifecycle_and_billing_tables.php)
  - [database/migrations/2026_03_05_001300_add_subscription_plan_change_schedule_columns.php](../database/migrations/2026_03_05_001300_add_subscription_plan_change_schedule_columns.php)
  - [database/migrations/2026_03_05_000100_add_plan_to_tenants_and_create_subscription_audit_logs_table.php](../database/migrations/2026_03_05_000100_add_plan_to_tenants_and_create_subscription_audit_logs_table.php)
- [routes/api.php](../routes/api.php)
- [routes/web.php](../routes/web.php)

Tambahan non-code yang masih penting:
- definisi resmi paket bisnis S, M, L,
- aturan trial resmi,
- aturan pembayaran awal,
- apakah paket S gratis selamanya,
- apakah `L` memang pengganti `P` atau konsep berbeda.

## C. Ringkasan Satu Kali Kirim

Project saya adalah aplikasi kasir Laravel multi-tenant dengan modul POS, inventory, purchases, customers, suppliers, finance, analytics, production batch, observability, loyalty, users, branches, dan subscription billing. Onboarding saat ini berjalan lewat step 1 sampai 4 plus review, membuat tenant dan owner, lalu redirect ke /pos/dashboard, tetapi saya belum menemukan pembuatan subscription awal otomatis di flow onboarding.

Sistem paket yang saat ini benar-benar dipakai di codebase adalah S, M, P, bukan S, M, L. Definisinya ada di config/feature-gating.json, app/Support/FeatureGateConfig.php, dan app/Services/SubscriptionService.php. Label plan saat ini adalah Starter, Medium, Pro. Harga yang terlihat di kode adalah S = 0, M = 199000, P = 399000. Ini adalah inkonsistensi terhadap kebutuhan bisnis saya yang menulis S, M, L.

Subscription lifecycle yang sudah ada: active, trialing, past_due, suspended, canceled. Trial ada secara teknis melalui kolom trial_ends_at dan lifecycle command. Grace period ada melalui grace_ends_at. Pending activation tidak ditemukan. Expired tidak ditemukan. Invoice dan payment flow sudah ada melalui tabel invoices, invoice_items, subscription_payments, subscription_operations dan controller SubscriptionInvoiceController. Scheduled downgrade atau pending plan change juga sudah ada melalui pending_plan dan pending_change_effective_at.

Masalah arsitektur utama: current plan aktif dibaca dari tenants.plan melalui SubscriptionService, sedangkan subscriptions.plan juga menyimpan plan di row subscription. Jadi ada duplikasi sumber data plan. Current status dibaca dari current subscription row, tetapi currentStatus fallback ke active jika row subscription tidak ada. Ini berisiko membuat tenant terlihat aktif walau belum punya current subscription. Risiko drift juga ada karena tenant.plan dan subscriptions.plan bisa tidak sinkron.

Feature gating terpusat di config/feature-gating.json. Service utama adalah FeatureGateConfig dan SubscriptionService. Middleware utama adalah FeatureGateMiddleware, SubscriptionStatusMiddleware, dan WebAccessMiddleware. Beberapa area premium seperti valuation memakai kombinasi feature gate plus subscription status active atau trialing. Namun enforcement tampaknya belum konsisten penuh untuk semua feature key, karena banyak route web memakai gate umum dash.overview atau hanya role. Contoh area yang jelas premium di config: dash.control, history.audit_log, reports.inventory_stock_card, reports.inventory_fifo_layers, finance.balance_sheet, finance.cash_flow, finance.lock_period, product.valuation, settings.coa, settings.roles_permissions, settings.multi_branch, settings.inventory_valuation, settings.observability, settings.integrations. Area menengah yang terlihat: search.global, notification.list, pos.customer, shift.print_summary, reports.analytics_payments, reports.purchases_view, reports.download_pdf, reports.export_csv, product.materials, purchase.return, customer.manage, loyalty.program, settings.tax, settings.promo_default, settings.staff_basic, settings.branches_manage, settings.notifications.

Laporan yang sudah ada dan terlihat jelas di codebase: finance summary, sales history summary dan detail, inventory summary, stock alert, stock card, analytics dashboard, finance control daily KPI, valuation report export, observability dashboard, anomaly listing, refund legacy audit, subscription current, entitlements, impact preview, audit logs, dan invoices. FinanceReportController memberi profit/loss dan menampilkan balance sheet hanya jika entitlement finance.balance_sheet aktif. InventoryReportController memberi sku_count, low_stock_count, out_of_stock_count, total_value, movement, audit trail, recent movements. AnalyticsController memberi revenue, orders, profit, top products, payment mix, trend. SalesHistoryController memberi gross sales, discounts, refunds, tax, net total, payment mix, top products, average ticket. ObservabilityController memberi queue depth, summary lag, anomaly count by severity and type, detector metrics.

File paling penting untuk topik ini: SubscriptionService, SubscriptionController, SubscriptionInvoiceController, FeatureGateConfig, FeatureGateMiddleware, SubscriptionStatusMiddleware, WebAccessMiddleware, OnboardingController, OnboardingProvisioningService, OnboardingFlowStateService, EnforceSubscriptionLifecycleCommand, feature-gating.json, migration subscriptions, migration billing, migration audit logs, Tenant model. RegistrationSubscriptionService tidak ditemukan di codebase.

Temuan paling penting yang harus diperhatikan: enum bisnis S/M/L belum cocok dengan implementasi S/M/P, onboarding belum terlihat membuat subscription awal, currentStatus fallback ke active terlalu permisif, tenant.plan adalah source of truth entitlement sementara subscriptions juga menyimpan plan, dan belum pasti seluruh route sudah menegakkan feature gate spesifik secara konsisten.