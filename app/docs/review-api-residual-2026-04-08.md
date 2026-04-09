# Review API Residual 2026-04-08

## Kesimpulan

`routes/api.php` bukan residual kecil. File ini memperluas surface API secara signifikan dan mengubah kebijakan akses inti. Batch ini harus diperlakukan terpisah dari PR audit final yang hanya fokus pada observability bootstrap, checkout, dan finance control.

## Perubahan Utama

### 1. Auth API Menjadi Wajib

Temuan utama:

- Middleware API sekarang dipaksa menjadi `auth:sanctum` untuk seluruh group `/api/v1`.

Implikasi:

- Client lokal, smoke tools, atau flow lama yang sebelumnya tidak mengirim auth akan pecah.
- Ini perubahan perilaku global, bukan lokal pada satu endpoint.

### 2. Surface API Baru Ditambahkan

Temuan utama:

- Inventory reports ditambahkan, termasuk summary, low stock, export CSV/PDF, dan stock card export.
- Refund APIs ditambahkan untuk listing, detail, create, dan legacy audit/remediation.
- Subscription APIs ditambahkan untuk current plan, entitlements, impact preview, audit logs, invoice list/detail/download, dan mark paid.
- Surface platform admin baru muncul lewat controller dan request object terpisah.

Implikasi:

- Ini sudah setara beberapa feature batch baru.
- Butuh review domain dan test contract sendiri.

### 3. Role Guard Diperluas

Temuan utama:

- Banyak endpoint yang sebelumnya `admin` saja sekarang melibatkan `owner`, `warehouse`, `supervisor`, atau `superadmin`.
- Checkout, shift, purchases, stock transfer, refund, dan subscription memakai kombinasi role yang lebih luas.

Implikasi:

- Permission matrix berubah besar.
- Regression yang mungkin muncul adalah 403 baru atau akses berlebih pada role tertentu.

### 4. Feature Gate dan Subscription Gate Aktif

Temuan utama:

- Endpoint valuation sekarang memakai kombinasi middleware `feature_gate:settings.inventory_valuation` dan `subscription_status:active,trialing`.
- Observability health juga digate oleh feature gate.

Implikasi:

- Perubahan akses valuation sekarang bergantung ke paket dan status langganan, bukan hanya role.
- Jika entitlements atau subscription state salah, hasilnya bukan error fungsional biasa tetapi 403 di level route.

### 5. Platform Surface Muncul sebagai Batch Mandiri

Temuan utama:

- Folder `app/Http/Controllers/Api/Platform` menambah endpoint tenant, user, invoice, anomaly, audit log, support, settings, dashboard, payment, dan plan change.
- Folder `app/Http/Requests/Platform` menambah banyak request contract untuk surface ini.

Implikasi:

- Ini seharusnya direview sebagai feature/platform admin batch sendiri.
- Tidak tepat ikut ke PR observability audit yang sempit.

## Risiko Terbesar

1. Breaking change pada client yang belum selalu membawa Sanctum auth.
2. Permission drift akibat ekspansi role guard.
3. 403 baru pada endpoint valuation karena gating subscription/feature.
4. Surface refund, subscription, dan platform terbawa tanpa review menyeluruh.
5. Mismatch antara route baru dan middleware baru yang belum direview sebagai satu paket.

## Rekomendasi

1. Pisahkan `routes/api.php` dari batch audit final.
2. Review API residual ini bersama middleware akses dan subscription gate.
3. Buat batch terpisah untuk:
   - refund + refund legacy audit
   - subscription + invoices
   - platform admin APIs
   - inventory reporting
4. Pastikan ada contract test atau smoke test per surface baru sebelum merge.