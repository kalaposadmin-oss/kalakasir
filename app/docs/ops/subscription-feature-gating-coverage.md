# Subscription Feature Gating Coverage

Status audit per 2026-04-04 untuk `config/feature-gating.json` setelah cleanup final.

Definisi:
- `LIVE`: sudah benar-benar dipakai di implementasi saat ini dan enforce subscription di surface aktif.
- `DORMANT`: didefinisikan di config, tetapi belum punya wiring nyata di route/UI/API aktif.
- `DRIFT`: ada surface atau flow terkait di aplikasi, tetapi implementasi aktif tidak lagi memakai key ini sebagai source of truth.

Catatan:
- Penilaian ini fokus pada coverage runtime aktif, bukan hanya keberadaan key di `menu-registry.php`.
- Key yang hanya muncul di `config/menu-registry.php` tetapi tidak dipakai oleh navigasi/runtime aktif tetap dianggap `DORMANT` atau `DRIFT`.

## LIVE

| Key | Related surface | Evidence | Decision |
| --- | --- | --- | --- |
| `dash.overview` | dashboard dan broad backoffice/pos entry | dipakai luas di `web_access` untuk route aktif di [web.php](/F:/KASIRR/kalakasir/app/routes/web.php) | keep |
| `reports.summary` | report overview/sales | dipakai di route report aktif di [web.php](/F:/KASIRR/kalakasir/app/routes/web.php) | keep |
| `reports.inventory_view` | inventory report | web route dan controller API aktif di [web.php](/F:/KASIRR/kalakasir/app/routes/web.php) dan [InventoryReportController.php](/F:/KASIRR/kalakasir/app/app/Http/Controllers/Api/InventoryReportController.php) | keep |
| `reports.inventory_stock_card` | stock card report/export | web route dan API export aktif di [web.php](/F:/KASIRR/kalakasir/app/routes/web.php) dan [InventoryReportController.php](/F:/KASIRR/kalakasir/app/app/Http/Controllers/Api/InventoryReportController.php) | keep |
| `reports.download_pdf` | inventory report PDF export | CTA Blade + API check di [reports-inventory.blade.php](/F:/KASIRR/kalakasir/app/resources/views/reports-inventory.blade.php) dan [InventoryReportController.php](/F:/KASIRR/kalakasir/app/app/Http/Controllers/Api/InventoryReportController.php) | keep |
| `reports.export_csv` | inventory report CSV export | CTA Blade + API check di [reports-inventory.blade.php](/F:/KASIRR/kalakasir/app/resources/views/reports-inventory.blade.php) dan [InventoryReportController.php](/F:/KASIRR/kalakasir/app/app/Http/Controllers/Api/InventoryReportController.php) | keep |
| `finance.balance_sheet` | premium finance summary data | conditional API exposure di [FinanceReportController.php](/F:/KASIRR/kalakasir/app/app/Http/Controllers/Api/FinanceReportController.php) dan UI tab di [finance-summary.blade.php](/F:/KASIRR/kalakasir/app/resources/views/finance-summary.blade.php) | keep |
| `settings.inventory_valuation` | valuation settings/audit/rebuild/reporting | web route, page guard, API feature gate, dan lifecycle guard di [web.php](/F:/KASIRR/kalakasir/app/routes/web.php), [api.php](/F:/KASIRR/kalakasir/app/routes/api.php), [inventory-valuation-settings.blade.php](/F:/KASIRR/kalakasir/app/resources/views/inventory-valuation-settings.blade.php), [inventory-valuation-audit.blade.php](/F:/KASIRR/kalakasir/app/resources/views/inventory-valuation-audit.blade.php) | keep |
| `settings.observability` | observability page + API | web route dan API `observability/health` di [web.php](/F:/KASIRR/kalakasir/app/routes/web.php) dan [api.php](/F:/KASIRR/kalakasir/app/routes/api.php) | keep |
| `settings.integrations` | integrations page | web route + page guard di [web.php](/F:/KASIRR/kalakasir/app/routes/web.php) dan [settings-integrations.blade.php](/F:/KASIRR/kalakasir/app/resources/views/settings-integrations.blade.php) | keep |

## REMOVED

| Key | Previous mismatch | Cleanup |
| --- | --- | --- |
| `reports.analytics_sales` | sales report aktif memakai `reports.summary`, bukan key ini | removed from `feature-gating.json`; menu registry diselaraskan ke `reports.summary` |
| `settings.audit_log` | valuation audit aktif memakai `settings.inventory_valuation`, bukan key ini | removed from `feature-gating.json`; menu registry diselaraskan ke `settings.inventory_valuation` |

## DRIFT

| Key | Related surface | Current mismatch | Decision |
| --- | --- | --- | --- |
| `help.center` | `/backoffice/help/center` | page aktif ada, tetapi route/page tidak memakai key ini | keep dormant for now, wire later if help becomes subscription-aware |
| `pos.checkout` | POS checkout | flow aktif ada, tetapi akses ditentukan portal/role, bukan feature key | keep dormant for now |
| `shift.open` | `/pos/shifts/open` | flow aktif ada, key hanya hidup di config/menu registry | keep dormant for now |
| `shift.close` | `/pos/shifts/close` | flow aktif ada, key hanya hidup di config/menu registry | keep dormant for now |
| `shift.history` | `/pos/shifts/summary` dan wrappers | flow aktif ada, tetapi tidak memakai key ini | keep dormant for now |
| `history.sales` | `/pos/history` dan `/backoffice/reports/sales` | surface aktif ada, tetapi tidak memakai key ini | keep dormant for now |
| `finance.profit_loss` | `/backoffice/finance/summary` tab laba rugi | surface aktif ada, tapi belum memakai key ini | keep dormant for now |
| `finance.lock_period` | `/backoffice/finance/lock-periods` | surface aktif ada, tapi belum memakai key ini | keep dormant for now |
| `product.list` | `/backoffice/products` | route aktif memakai `dash.overview`, bukan key ini | candidate wire later if product module becomes plan-aware |
| `product.category` | `/backoffice/categories` | surface aktif ada, tapi tidak memakai key ini | keep dormant for now |
| `product.materials` | `/backoffice/materials` | surface aktif ada, tapi tidak memakai key ini | keep dormant for now |
| `product.pricing` | create/edit products | pricing UI aktif ada, tapi tidak memakai key ini | keep dormant for now |
| `inventory.stock` | `/backoffice/inventory` | surface aktif ada, tapi tidak memakai key ini | keep dormant for now |
| `inventory.adjustment` | `/backoffice/inventory/stock-adjustment` | surface aktif ada, tapi tidak memakai key ini | keep dormant for now |
| `inventory.transfer` | `/backoffice/inventory/stock-transfers` | surface aktif ada, tapi tidak memakai key ini | keep dormant for now |
| `purchase.order` | purchase create/detail | surface aktif ada, tapi tidak memakai key ini | keep dormant for now |
| `purchase.receive` | receiving wrappers | surface aktif ada, tapi tidak memakai key ini | keep dormant for now |
| `purchase.return` | purchase return page | surface aktif ada, tapi tidak memakai key ini | keep dormant for now |
| `purchase.stock_purchase` | CTA ringan di inventory report | key hanya dipakai untuk affordance UI, bukan gate nyata | candidate remove if not needed |
| `supplier.manage` | `/backoffice/suppliers` | surface aktif ada, tapi tidak memakai key ini | keep dormant for now |
| `customer.manage` | `/backoffice/customers` | surface aktif ada, tapi tidak memakai key ini | keep dormant for now |
| `loyalty.program` | `/backoffice/loyalty` | surface aktif ada, tapi tidak memakai key ini | keep dormant for now |
| `settings.store_profile` | settings/preferences family | surface serupa ada, tapi key tidak dipakai | keep dormant for now |
| `settings.subscription_billing` | subscription pages | surface aktif ada, tetapi billing module saat ini tidak memakai key ini | keep dormant for now |
| `settings.tax` | `/backoffice/settings/tax-promo` | surface aktif ada, tetapi route/page tidak memakai key ini | keep dormant for now |
| `settings.promo_default` | `/backoffice/settings/tax-promo` | surface aktif ada, tetapi route/page tidak memakai key ini | keep dormant for now |
| `settings.staff_basic` | `/backoffice/users` | surface aktif ada, tapi tidak memakai key ini | keep dormant for now |
| `settings.branches_manage` | `/backoffice/branches` | surface aktif ada, tapi tidak memakai key ini | keep dormant for now |
| `settings.coa` | `/backoffice/settings/chart-of-accounts` | surface aktif ada, tapi tidak memakai key ini | keep dormant for now |
| `settings.multi_branch` | branch management concept | implementation aktif ada, key tidak dipakai | keep dormant for now |

## DORMANT

| Key | Related files/routes if any | Current state | Decision |
| --- | --- | --- | --- |
| `onboarding.flow` | none | no active runtime wiring found | keep dormant |
| `search.global` | none | no active runtime wiring found | keep dormant |
| `notification.list` | none | no active runtime wiring found | keep dormant |
| `dash.insights` | none | no active runtime wiring found | keep dormant |
| `dash.control` | none | no active runtime wiring found | keep dormant |
| `pos.customer` | no dedicated feature-gated route found | customer helper exists in POS UI, but no key wiring | keep dormant |
| `pos.hold_bill` | none | no active runtime wiring found | keep dormant |
| `pos.split_payment` | none | no active runtime wiring found | keep dormant |
| `pos.refund` | none | no active runtime wiring found | keep dormant |
| `pos.void` | no direct key wiring found | action exists in flow, but key is not source of truth | keep dormant |
| `shift.print_summary` | none | no active runtime wiring found | keep dormant |
| `history.payments` | none | no active runtime wiring found | keep dormant |
| `history.stock` | none | no active runtime wiring found | keep dormant |
| `history.audit_log` | none | no active runtime wiring found | keep dormant |
| `reports.analytics_products` | none | no dedicated active page found | keep dormant |
| `reports.analytics_payments` | none | no dedicated active page found | keep dormant |
| `reports.purchases_view` | none | no dedicated active report route found | keep dormant |
| `reports.inventory_fifo_layers` | valuation detail concept only | no dedicated runtime wiring found | keep dormant |
| `finance.cash_flow` | none | no active page/API for cash flow found | keep dormant |
| `product.valuation` | none | no dedicated active surface found | keep dormant |
| `settings.printer_receipt` | none | no active runtime wiring found | keep dormant |
| `settings.devices` | none | no active runtime wiring found | keep dormant |
| `settings.payments` | none | no active runtime wiring found | keep dormant |
| `settings.notifications` | none | no active runtime wiring found | keep dormant |
| `settings.roles_permissions` | none | no active runtime wiring found | keep dormant |

## Final Action Summary

- `REMOVE from config`:
  `reports.analytics_sales`, `settings.audit_log`
- `KEEP as future dormant key`:
  seluruh key non-live lain yang masih merepresentasikan modul/fitur nyata atau roadmap yang masih masuk akal
- `WIRE into actual UI/route/API`:
  tidak ada tambahan wiring di cleanup pass ini; runtime premium gating sudah ditutup di pass sebelumnya

## Practical Conclusion

- Subscription gating bisa disebut `COMPLETE` untuk scope saat ini:
  runtime premium gating yang live sudah aman, alias obsolete yang jelas sudah dihapus, dan key non-live yang tersisa sudah diposisikan eksplisit sebagai dormant future keys.
- Artinya, `feature-gating.json` kini kembali akurat sebagai source of truth:
  `LIVE` berarti benar-benar enforced, `REMOVED` berarti memang tidak dipakai lagi, dan sisanya sengaja dipertahankan sebagai dormant roadmap keys.

## Recommended Next Cleanup

- Jika modul-modul berikut memang akan dibatasi paket di masa depan, wire key existing alih-alih membuat key baru:
  `product.list`, `inventory.stock`, `purchase.order`, `customer.manage`, `loyalty.program`, `settings.tax`, `settings.coa`
- Jika tidak ada rencana produk, pertahankan sebagai `DORMANT` dan jangan dipakai sebagai dasar keputusan runtime.
