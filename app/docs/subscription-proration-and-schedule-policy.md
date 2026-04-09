# Subscription Proration and Schedule Policy

Dokumen ini mendefinisikan kontrak final untuk perubahan plan langganan.

## 1. Sumber Waktu dan Timezone

- Semua perhitungan backend menggunakan timezone `UTC`.
- `effective_at`, `current_period_start`, dan `current_period_end` disimpan dan dievaluasi dalam `UTC`.
- UI boleh menampilkan tanggal dalam timezone lokal pengguna, tetapi nilai sumber tetap dari backend.

## 2. Definisi Periode untuk Proration

- Proration menggunakan `actual_subscription_period`.
- Formula rasio:
  - `ratio_remaining = (period_end - now_utc) / (period_end - period_start)`
  - Nilai dibatasi antara `0` dan `1`.
- Harga plan saat ini:
  - `S = 0`
  - `M = 199000`
  - `P = 399000`
- Delta harga dihitung sebagai `new_plan_price - old_plan_price`.

## 3. Rounding Proration

- Rounding amount menggunakan `round_2_half_up` (2 desimal).
- Nilai `proration.amount` konsisten di preview dan hasil perubahan plan.

## 4. Aturan Mode Perubahan Plan

- `auto`:
  - Upgrade => `immediate`
  - Downgrade => `end_of_period`
- `immediate`:
  - Plan aktif berubah langsung.
  - Jika ada pending downgrade, pending akan dibersihkan.
- `end_of_period`:
  - Plan aktif tidak berubah sekarang.
  - `pending_plan` dijadwalkan di akhir periode berjalan.

## 5. Edge Cases

- No-op (`old_plan == new_plan`):
  - API mengembalikan `status=no_changes`.
  - Tidak membuat audit log perubahan plan.
- Retry/double request dengan `X-Idempotency-Key` sama:
  - Response direplay dari `subscription_operations`.
  - Tidak membuat duplikasi audit/pending/payment.
- Scheduled downgrade overwrite:
  - Kebijakan final: `replace_existing`.
  - Jadwal lama ditimpa jadwal terbaru.
- Upgrade saat ada pending downgrade:
  - Upgrade immediate diterapkan.
  - Pending downgrade dibersihkan (`pending_cleared=true`).

## 6. Observability Events

- `subscription.plan.changed`
- `subscription.plan.change_scheduled`
- `subscription.plan.change_applied`
- `subscription.invoice.mark_paid`
- Metric log berbasis structured event:
  - `metric.subscription.downgrade_scheduled`
  - `metric.subscription.proration_invoice_created`
  - `metric.subscription.suspended`
