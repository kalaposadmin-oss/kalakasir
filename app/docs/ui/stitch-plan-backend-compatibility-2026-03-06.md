# Stitch UI Compatibility Check (Backend User)

Tanggal cek: 2026-03-06

Tujuan: memastikan folder referensi UI `stitch_plan_comparison_upgrade_screen` cocok dengan backend user yang sudah ada.

Prinsip:
- File stitch hanya referensi tampilan.
- Sistem tetap pakai route/API/feature gate yang sudah berjalan.
- Label plan final mengikuti sistem (`S/M/P`) dengan `plan_labels` dari API.

## Hasil cepat
- Total layar referensi: 18
- Lengkap aset per layar (`code.html` + `screen.png`): 18/18
- Match tinggi: 8
- Partial (butuh adaptasi UI ke sistem): 8
- Gap (fitur belum ada persis): 2

## Mapping per layar
| Folder Stitch | Intent UI | Target Sistem Kita | Status | Catatan Adaptasi |
| --- | --- | --- | --- | --- |
| `invoice_history_management` | Invoice history + payment management | `subscription-invoices.blade.php`, API `/api/v1/subscription/invoices*` | Match | Sudah ada list/detail/download/mark paid. |
| `plan_comparison_features` | Tabel perbandingan paket & CTA upgrade | `subscription-change.blade.php` + `subscription-features.blade.php` | Partial | UI compare tabel bisa diterapkan, plan naming harus `S/M/P` (hindari hardcode Gold/Platinum). |
| `subscription_overview_dashboard_1` | Pratinjau perubahan paket | `subscription-change.blade.php` + `/api/v1/subscription/impact-preview` | Match | Sudah ada preview mode/effective/proration + konfirmasi downgrade. |
| `subscription_overview_dashboard_2` | Profil billing bisnis | Belum ada halaman khusus billing profile | Gap | Bisa dipetakan ke `branches`/profil tenant, tapi form billing dedicated belum ada. |
| `subscription_overview_dashboard_3` | Inventory overview + upgrade gate | `reports-inventory.blade.php`, `inventory.blade.php` | Match | KPI stok/low stock/reorder sudah ada basis API. |
| `subscription_overview_dashboard_4` | Staff & role management | `users.blade.php` + API `/api/v1/users` | Match | Role/staff CRUD sudah ada (dengan gate role+feature). |
| `subscription_overview_dashboard_5` | Invoice history + detail modal | `subscription-invoices.blade.php` | Match | Detail invoice dan item sudah ada. |
| `subscription_overview_dashboard_6` | Stock opname management | Tidak ada module stock opname dedicated | Gap | Saat ini paling dekat ke `stock-adjustment`; perlu keputusan produk jika ingin 1:1. |
| `subscription_overview_dashboard_7` | Subscription audit log + export | `subscription-history.blade.php`, API `/api/v1/subscription/audit-logs*` | Partial | Data ada; UI tabel filter lanjutan/export badge perlu penyesuaian gate kita. |
| `subscription_overview_dashboard_8` | Langganan & billing overview + kuota | `subscription-current.blade.php` | Partial | Pending plan dan status ada; konsep kuota/query stats belum ada di kontrak API. |
| `subscription_overview_dashboard_9` | Subscription dashboard + usage stats | `subscription-current` + `subscription-history/invoices` | Partial | Komponen history ada, usage/query stats belum ada endpoint khusus. |
| `subscription_overview_dashboard_10` | Sales report dashboard + export | `analytics.blade.php`, `sales-history.blade.php` | Partial | Export di sistem: CSV/PDF, bukan Excel/XLSX. |
| `subscription_overview_dashboard_11` | Main admin dashboard | `welcome.blade.php`, `menu.blade.php`, `analytics` | Partial | KPI gabungan bisa diadopsi; beberapa kartu perlu agregasi endpoint tambahan. |
| `subscription_overview_dashboard_12` | Riwayat transaksi + ringkasan | `sales-history.blade.php`, `sales-detail.blade.php` | Match | Alur filter transaksi sudah tersedia. |
| `subscription_overview_dashboard_13` | Product catalog management | `products.blade.php`, `product-detail.blade.php`, `product-edit.blade.php` | Match | CRUD produk sudah tersedia. |
| `subscription_overview_dashboard_14` | Sales analytics lanjutan (chart/heatmap) | `analytics.blade.php` | Partial | Ringkasan/payments/top products ada; heatmap jam jual belum ada. |
| `subscription_overview_dashboard_15` | Subscription plans cards | `subscription-change.blade.php` | Match | Mode auto/immediate/end_of_period sudah ada; tinggal styling.
| `subscription_overview_dashboard_16` | Branch & staff management | `branches.blade.php` + `users.blade.php` | Match | Kedua modul sudah ada dan live. |

## Risiko mismatch yang harus dijaga
1. Plan naming di mockup: banyak pakai `Gold/Platinum/Professional`.
   - Implementasi final harus pakai data API (`plan`, `plan_labels`) dan policy `S/M/P`.
2. Export label di mockup: beberapa layar menulis `Excel/XLSX`.
   - Sistem saat ini resmi `CSV/PDF` via feature gate `reports.export_csv`, `reports.download_pdf`.
3. Komponen desktop sidebar.
   - Aplikasi kita dominan shell mobile (`max-w-[430px]`). Layout desktop bisa diadaptasi, bukan copy-paste mentah.
4. Fitur kuota/AI usage stats.
   - Belum ada endpoint dedicated di backend saat ini.

## Kesimpulan
- Referensi stitch secara umum kompatibel untuk backend user kita.
- Mayoritas layar bisa langsung dipakai sebagai UI layer, dengan adaptasi naming/gating/export.
- Dua area yang belum 1:1 dengan backend saat ini: billing profile dedicated dan stock opname dedicated.

## Rekomendasi implementasi bertahap
1. Fase 1 (langsung implement visual): `1,3,4,5,12,13,15,16`.
2. Fase 2 (butuh adaptasi data/fitur ringan): `7,8,9,10,11,14,plan_comparison_features`.
3. Fase 3 (butuh keputusan produk): `2,6`.
