# Auto-Consume Pilot Rollout Readiness Checklist

Tanggal: 2026-03-13
Status: Draft for PR-C

## Scope Kecil PR-C
- Tambahan test matrix edge case auto-consume.
- Observability minimum: auto_consume.started, auto_consume.succeeded, auto_consume.failed, auto_consume.skipped.
- Pilot rollout readiness dengan checklist verifikasi operasional.

## Checklist No-Regression
- [x] Default flag tenant tetap off (`auto_consume_on_sell_enabled = false`).
- [x] Enable/disable per-tenant diverifikasi via policy tests.
- [x] CheckoutService tetap tipis (hanya memanggil hook service).
- [x] Observability dipusatkan di AutoConsumeOnSellService.
- [x] Error operasional jelas untuk kondisi recipe invalid dan insufficient raw material.

## Observability Minimum (Context)
- tenant_id
- sale_id
- sale_item_id
- product_id
- recipe_id (nullable)
- raw_material_product_id (nullable)
- qty_requested
- qty_consumed
- policy_negative_stock
- error_code (nullable)

## Pilot Debug Guide
Gunakan `valuation_audit_logs.action` untuk investigasi:
- `auto_consume.started`: hook dipanggil.
- `auto_consume.skipped`: hook tidak menulis konsumsi (cek `skip_reason` pada `meta_json`).
- `auto_consume.succeeded`: konsumsi berhasil untuk input recipe tertentu.
- `auto_consume.failed`: hook gagal dan checkout dibatalkan.

Pertanyaan operasional: "stok bahan tiba-tiba berkurang"
1. Filter `valuation_audit_logs` by `tenant_id`, `action LIKE auto_consume.%`, rentang waktu transaksi.
2. Cocokkan `sale_id` + `sale_item_id` + `raw_material_product_id`.
3. Verifikasi `qty_requested`, `qty_consumed`, `policy_negative_stock`, dan `error_code`.
4. Korelasikan dengan `stock_ledger.reference_type = sale_recipe_input`.
