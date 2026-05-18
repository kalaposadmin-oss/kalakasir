# Subscription Feature Gating Coverage

Status audit per 2026-04-12 untuk `config/feature-gating.json` setelah cleanup runtime alignment batch terbaru.

Definisi:
- `LIVE`: key sudah dipakai oleh route, API, view guard, atau affordance runtime yang aktif.
- `DORMANT`: key masih ada di config, tetapi belum menjadi source of truth runtime yang tegas.
- `REMOVED`: key sudah dibersihkan dari config aktif karena tidak jujur terhadap runtime saat ini.

Catatan:
- Audit ini mengikuti runtime aktif di web/API, bukan sekadar keberadaan key di registry atau enum lama.
- Key yang belum punya wiring runtime spesifik tidak boleh dijual sebagai entitlement aktif.
- `refund-legacy-audit` masih memakai guard generik dan belum layak punya key premium terpisah.

## LIVE

| Key | Related surface | Evidence | Decision |
| --- | --- | --- | --- |
| `dash.overview` | dashboard utama dan surface marketing umum | dipakai oleh dashboard portal aktif dan page broad marketing/help yang memang tidak punya entitlement operasional lebih spesifik | keep |
| `help.center` | help center, about, support | route help aktif memakai key spesifik di `routes/web.php` | keep |
| `pos.checkout` | checkout POS | route `/pos/checkout` memakai key spesifik di `routes/web.php` | keep |
| `pos.customer` | customer picker POS | web POS memakai endpoint lookup khusus dan Android source juga memakai key ini untuk mengunci customer feature | keep |
| `pos.void` | void/refund POS | route web POS dan API `sales/{sale}/void` sekarang memakai key spesifik ini | keep |
| `shift.open` | halaman buka shift | route `/pos/shifts/open` dan API `shifts/open` memakai key spesifik ini | keep |
| `shift.close` | halaman tutup shift | route `/pos/shifts/close` dan API `shifts/close` memakai key spesifik ini | keep |
| `shift.history` | ringkasan shift | route summary shift memakai key spesifik di `routes/web.php` | keep |
| `shift.print_summary` | print shift summary | web shift summary dan Android source memakai key ini untuk mengunci affordance print summary | keep |
| `history.sales` | riwayat penjualan POS | route `/pos/history` memakai key spesifik di `routes/web.php` | keep |
| `history.purchases` | list dan detail pembelian | web route dan API pembelian aktif memakai key ini | keep |
| `reports.summary` | report overview/sales | route report aktif memakai key ini | keep |
| `reports.purchases_view` | laporan pembelian formal | route web `/backoffice/reports/purchases` dan Android source memakai key ini untuk purchase report affordance | keep |
| `reports.inventory_view` | inventory report | web route inventory report aktif memakai key ini | keep |
| `reports.inventory_stock_card` | stock card report dan detail | web route dan API export stock card aktif memakai key ini | keep |
| `reports.download_pdf` | export PDF report inventory | CTA view dan API export aktif memakai key ini | keep |
| `reports.export_csv` | export CSV report inventory | CTA view dan API export aktif memakai key ini | keep |
| `finance.summary_basic` | halaman ringkasan keuangan dan finance-control-daily API | web route, API finance summary, dan finance-control-daily API aktif memakai key ini | keep |
| `finance.bank_accounts` | akun bank dan kas | web route, API chart-of-accounts, dan API transaksi akun aktif memakai key ini | keep |
| `finance.balance_sheet` | tab dan exposure finance premium | UI dan conditional API exposure aktif memakai key ini | keep |
| `finance.lock_period` | lock period | web route dan API lock period aktif memakai key ini | keep |
| `product.list` | daftar, detail, create, edit produk | route web produk dan API read/write produk aktif memakai key spesifik ini | keep |
| `product.category` | daftar dan create kategori | route web kategori dan API read/write kategori aktif memakai key spesifik ini | keep |
| `product.materials` | master bahan baku | web route dan API materials aktif memakai key ini | keep |
| `product.recipe_management` | recipe/BOM di create-edit product | Blade dan API product memakai key ini untuk gating recipe management | keep |
| `inventory.stock` | halaman inventory | route inventory aktif memakai key spesifik di `routes/web.php` | keep |
| `inventory.adjustment` | stock adjustment | route web stock adjustment dan API mutate stock adjustment aktif memakai key spesifik ini | keep |
| `inventory.transfer` | stock transfer | web route dan API stock transfer aktif memakai key ini | keep |
| `inventory.production_batches` | production batches | web route dan API production batch aktif memakai key ini | keep |
| `purchase.order` | create purchase | route web create purchase dan API `POST /purchases` aktif memakai key spesifik ini | keep |
| `purchase.receive` | receiving index, detail, action | route receiving web dan API action receive/decision/close aktif memakai key spesifik ini | keep |
| `purchase.return` | purchase return | route purchase return web dan API `POST /purchase-returns` aktif memakai key spesifik ini | keep |
| `purchase.stock_purchase` | CTA stock purchase di inventory report | Blade inventory report memakai key ini untuk affordance aktif | keep |
| `supplier.manage` | halaman supplier | web supplier dan `GET /api/v1/suppliers` sama-sama memakai key ini | keep |
| `customer.manage` | halaman pelanggan | web pelanggan dan `GET /api/v1/customers` sama-sama memakai key ini | keep |
| `loyalty.program` | halaman loyalty | web loyalty dan `GET /api/v1/loyalty` sama-sama memakai key ini | keep |
| `settings.store_profile` | preferences/store profile | route settings preferences aktif memakai key spesifik di `routes/web.php` | keep |
| `settings.subscription_billing` | subscription, invoice, change plan | route langganan web dan API subscription tenant aktif memakai key ini | keep |
| `settings.tax` | pengaturan pajak dan promo | route tax/promo dan API update tax aktif memakai key ini | keep |
| `settings.staff_basic` | manajemen pengguna | route users dan API user-management aktif memakai key ini | keep |
| `settings.branches_manage` | manajemen cabang | route branch management dan API create-update branch aktif memakai key ini | keep |
| `settings.inventory_method_basic` | metode persediaan dasar | web route dan API patch valuation method aktif memakai key ini | keep |
| `settings.inventory_valuation` | valuation settings, audit, rebuild | web route, page guard, API guard, dan lifecycle guard aktif memakai key ini | keep |
| `settings.observability` | observability page, API health, dan anomaly API | web route, API health, dan anomaly API aktif memakai key ini | keep |
| `settings.integrations` | integrations page | web route dan page guard aktif memakai key ini | keep |

## DORMANT

| Key | Related surface | Current state | Decision |
| --- | --- | --- | --- |
| `none` | - | batch ini menutup seluruh sisa key config-only yang tidak punya runtime nyata dengan memindahkannya ke removed | no active dormant keys remain |

## REMOVED

| Key | Previous mismatch | Cleanup |
| --- | --- | --- |
| `reports.analytics_sales` | sales report aktif memakai `reports.summary`, bukan key ini | removed from active config |
| `settings.audit_log` | valuation audit aktif memakai `settings.inventory_valuation`, bukan key ini | removed from active config |
| `settings.coa` | runtime nyata adalah akun bank/kas, bukan modul COA terpisah | removed from active config and aligned to `finance.bank_accounts` |
| `dash.control` | tidak ada control dashboard premium yang benar-benar aktif | removed from active config |
| `history.audit_log` | tidak ada runtime gate jujur; surface tersisa masih generic/role-based | removed from active config |
| `reports.inventory_fifo_layers` | tidak ada surface runtime aktif yang layak dijual terpisah | removed from active config |
| `finance.cash_flow` | tidak ada surface runtime aktif yang memakai key ini | removed from active config |
| `onboarding.flow` | tidak dipakai sebagai subscription gate runtime; flow onboarding tetap berjalan tanpa entitlement key ini | removed from active config |
| `search.global` | hanya muncul di entitlements test, tidak punya runtime gate nyata | removed from active config |
| `notification.list` | tidak ada route, API, view, atau mobile source aktif yang memakai key ini | removed from active config |
| `dash.insights` | tidak ada route, API, view, atau mobile source aktif yang memakai key ini | removed from active config |
| `pos.hold_bill` | tidak ada runtime gate atau source code aktif yang memakai key ini | removed from active config |
| `pos.split_payment` | tidak ada runtime gate atau source code aktif yang memakai key ini | removed from active config |
| `pos.refund` | flow refund aktif menyatu di `pos.void`, bukan key terpisah | removed from active config |
| `history.payments` | tidak ada route atau API riwayat pembayaran yang memakai key ini | removed from active config |
| `history.stock` | tidak ada route atau API riwayat stok yang memakai key ini | removed from active config |
| `reports.analytics_products` | tidak ada page report analytics produk aktif yang memakai key ini | removed from active config |
| `reports.analytics_payments` | tidak ada page report analytics pembayaran aktif yang memakai key ini | removed from active config |
| `finance.profit_loss` | laba rugi masih menyatu di finance summary, bukan key entitlement terpisah | removed from active config |
| `finance.inventory_value_basic` | tidak ada widget gate runtime aktif yang memakai key ini | removed from active config |
| `product.pricing` | pricing masih menyatu di `product.list`, bukan key entitlement terpisah | removed from active config |
| `product.valuation` | runtime valuation hidup di settings inventory valuation, bukan product key ini | removed from active config |
| `settings.printer_receipt` | tidak ada route, API, atau view guard aktif yang memakai key ini | removed from active config |
| `settings.devices` | tidak ada route, API, atau view guard aktif yang memakai key ini | removed from active config |
| `settings.payments` | tidak ada route, API, atau view guard aktif yang memakai key ini | removed from active config |
| `settings.promo_default` | runtime aktif menyatu di `settings.tax`, bukan key terpisah | removed from active config |
| `settings.notifications` | tidak ada route, API, atau view guard aktif yang memakai key ini | removed from active config |
| `settings.roles_permissions` | belum ada surface runtime jujur berbasis key ini | removed from active config |
| `settings.multi_branch` | runtime aktif memakai `settings.branches_manage`, bukan key ini | removed from active config |

## Practical Conclusion

- Config aktif sekarang lebih jujur: key `LIVE` benar-benar punya surface runtime yang bisa dibuktikan.
- Batch ini mengosongkan sisa key dormant config-only yang tidak punya runtime nyata; roadmap boleh hidup di docs, tetapi tidak lagi dijual sebagai entitlement aktif.
- Key `REMOVED` juga sudah dibersihkan dari enum mobile batch ini agar tidak ada drift lintas platform terhadap key yang sudah tidak dijual lagi.
