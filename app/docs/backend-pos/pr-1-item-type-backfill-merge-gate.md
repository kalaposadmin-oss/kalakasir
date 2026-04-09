# PR-1 Item Type Backfill Merge Gate

Tanggal: 2026-03-13

## Catatan Rollback (Fail-Fast)

- Rollback migration untuk PR-1 memakai pendekatan fail-fast.
- Konsekuensi: proses rollback mengasumsikan langkah `up` sudah selesai penuh.
- Jika eksekusi `up` terputus di tengah, tim tidak mengandalkan rollback otomatis parsial. Lakukan investigasi manual sebelum rollback ulang.

## Gate Wajib Sebelum Merge

- Test rollback migration lulus.
- Test checkpoint query dengan `tenant_id = null` lulus.
- Lock TTL backfill `180` detik.
- `runOneBatch` selalu memanggil inisialisasi checkpoint row race-safe.
- Command audit memuat indikator:
  - `item_type_is_null`
  - `mode_add_on_not_raw_material`
  - `mode_bom_not_finished_good`

## Urutan Lanjut

1. Merge PR-1.
2. Lanjut PR-2 untuk `ProductController` dan UI admin.
3. Belum masuk flow produksi.
