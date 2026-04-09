# Backoffice Role Matrix

Dokumen ini merangkum keputusan akses dasar untuk area web/backoffice agar aturan tidak tersebar hanya di route dan middleware.

## Prinsip

- Gunakan fondasi `web_access` untuk halaman web/backoffice.
- Gunakan middleware `role:` untuk API yang sensitif atau operasional.
- Jangan buka area backoffice ke `cashier`.
- `warehouse` fokus pada inventory operasional dan laporan inventory, bukan laporan penjualan atau observability.
- `supervisor` boleh akses laporan operasional, tetapi tidak otomatis mendapat akses observability atau valuation audit.
- `admin` dan `owner` memegang akses paling luas pada area backoffice yang sensitif.

## Matriks Ringkas

| Area | Cashier | Warehouse | Supervisor | Admin | Owner |
| --- | --- | --- | --- | --- | --- |
| Backoffice sales report | No | No | Yes | Yes | Yes |
| Backoffice inventory report | No | Yes | Yes | Yes | Yes |
| Inventory audit trail feed | No | Yes | Yes | Yes | Yes |
| Inventory list / legacy inventory pages | No | Yes | Yes | Yes | Yes |
| Product catalog read pages | No | Yes | Yes | Yes | Yes |
| Product create / edit pages | No | No | No | Yes | No |
| Category read page | No | Yes | Yes | Yes | Yes |
| Category create / edit pages | No | No | No | Yes | No |
| Suppliers / PO overview page | No | Yes | Yes | Yes | Yes |
| Purchase detail read page | No | Yes | Yes | Yes | Yes |
| Purchase create / return / receiving action pages | No | No | No | Yes | No |
| Branch list page | No | Yes | Yes | Yes | Yes |
| Branch create / edit pages | No | No | No | Yes | No |
| Customer / loyalty read pages | No | Yes | Yes | Yes | Yes |
| Materials page | No | No | No | Yes | No |
| Users page | No | No | No | Yes | No |
| Chart of accounts pages | No | No | No | Yes | No |
| Tax promo / lock periods read pages | No | Yes | Yes | Yes | Yes |
| Subscription overview / invoices read pages | No | Yes | Yes | Yes | Yes |
| Subscription change page | No | No | No | Yes | Yes |
| Settings integrations / preferences read pages | No | Yes | Yes | Yes | Yes |
| Marketing online store / referral read pages | No | Yes | Yes | Yes | Yes |
| Stock alerts page | No | Yes | Yes | Yes | Yes |
| Stock transfer read API | No | Yes | Yes | Yes | Yes |
| Stock card API | No | Yes | Yes | Yes | Yes |
| Inventory alerts API | No | Yes | Yes | Yes | Yes |
| Product / supplier / purchase read API | Yes* | Yes | Yes | Yes | Yes |
| Stock adjustment mutate API | No | No | No | Yes | Yes |
| Stock transfer mutate API | No | No | No | Yes | No |
| Observability | No | No | No | Yes | Yes |
| Valuation audit page / API | No | No | No | Yes | Yes |

## Catatan

- Akses `owner` untuk stock transfer mutate belum diperluas karena rule existing saat ini masih `admin` only. Ini sengaja dipertahankan sampai ada kebutuhan bisnis yang jelas.
- Flow `void/refund` tetap mengikuti flow existing di POS dan halaman laporan/detail penjualan, bukan dibuka sebagai modul backoffice baru.
- Jika route web berubah di masa depan, guard tipis di view legacy inventory tetap diharapkan menjadi pengaman kedua.
- `Product / supplier / purchase` read API masih shared dengan flow POS dan backoffice, jadi `cashier` masih bisa mengenai endpoint baca tertentu. Enforcement backoffice untuk area ini tetap dipasang di route web dan page-entry guard.
- `Materials` saat ini diperlakukan sebagai halaman manajemen admin-only karena halaman legacy-nya berorientasi mutate dan belum punya kontrak read-only API yang terpisah di route registry saat ini.
