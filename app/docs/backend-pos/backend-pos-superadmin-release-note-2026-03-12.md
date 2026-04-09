# Release Note - Superadmin Backend

Tanggal rilis: 2026-03-12
Audience: Product, Operations, Support, Customer Success
Status: Ready

## Ringkasan

Rilis ini menuntaskan modul backend superadmin agar siap dipakai untuk operasional platform harian. Cakupan mencakup endpoint monitoring tenant, billing platform, support search, audit log, danger actions, penguatan idempotency, dan pengaturan rate limit terkonfigurasi.

## Dampak Bisnis

- Tim operasional platform dapat memonitor kesehatan tenant dan billing secara terpusat.
- Tim support dapat melakukan investigasi lintas tenant lebih cepat melalui support action feed dan global search.
- Risiko aksi ganda pada operasi sensitif menurun karena idempotency lock yang lebih ketat.
- Akuntabilitas tindakan admin meningkat melalui audit event yang konsisten.

## Fitur Utama yang Tersedia

- Dashboard platform untuk ringkasan tenant, user, event, dan status kesehatan.
- Manajemen tenant danger actions: suspend, unsuspend, impersonate, force logout, reset owner password, unlock.
- Global users: list, detail, dan audit trail per user.
- Billing platform: invoices, plan changes, payments, dan export.
- Monitoring: health checks, anomaly feed, resolve anomaly, audit logs dan export.
- Support tools: action timeline dan unified search.
- Platform settings: feature flags snapshot dan rate-limit snapshot.

## Keamanan dan Kepatuhan

- Idempotency-Key diwajibkan pada danger actions yang ditandai.
- Rate limit untuk danger actions diterapkan per admin.
- Header bypass internal tersedia untuk skenario internal terkontrol.
- Retensi audit log didukung purge otomatis harian berbasis policy.

## Validasi Kualitas

- Suite platform read API: pass.
- Suite platform danger actions: pass.
- Suite valuation dan write-flow: pass.
- Full regression: 127 passed, 524 assertions.

## Catatan Operasional

- Produksi direkomendasikan menggunakan Redis untuk stabilitas idempotency cache dan locking.
- Nilai rate limit dan idempotency TTL mengikuti konfigurasi environment platform.
- Gunakan checklist rilis sebelum deploy produksi untuk memastikan readiness lintas tim.

## Referensi

- Spec endpoint: docs/backend-pos/backend-pos-superadmin-endpoint-spec.md
- Changelog teknis: docs/backend-pos/backend-pos-superadmin-implementation-changelog-2026-03-12.md
- Checklist rilis: docs/backend-pos/backend-pos-superadmin-release-checklist-2026-03-12.md
