# PR5-PR9 APK Parity Delivery Note (2026-03-23)

## Ringkasan

Dokumen ini merangkum delivery berurutan untuk:

- PR5: stock ledger parity (ACK mirror)
- PR6: valuation parity (ACK mirror)
- PR7: accounting parity (ACK mirror)
- PR8: return valuation consistency hardening (ACK mirror)
- PR9: accounting mirror non-blocking hardening (ACK mirror)

Seluruh perubahan mengikuti guardrail:

- Backend ACK sebagai source of truth
- On-ack only (tanpa speculative mutation)
- Idempotent (aman terhadap replay)
- Tidak mengubah payload contract backend
- Patch kecil, scoped, tanpa refactor lintas layer besar

## File Scope

- `core-sync/src/main/java/com/umkm/offline/core/sync/SyncEngine.kt`
- `core-sync/src/test/java/com/umkm/offline/core/sync/SyncEngineTest.kt`
- `core-database/src/main/java/com/umkm/offline/core/database/dao/StockMovementDao.kt`
- `core-database/src/main/java/com/umkm/offline/core/database/OfflineDatabase.kt`
- `app/src/main/java/com/umkm/offline/app/MainActivity.kt`

## PR5 - Stock Ledger Parity

### Desain

- Saat ACK purchase receive diterima, tulis stock movement `IN`.
- Saat ACK purchase return diterima, tulis stock movement `OUT`.
- Gunakan deterministic identifier dan `sync_effects` guard agar replay tidak menduplikasi row.

### Acceptance Criteria

- Receive ACK menghasilkan ledger movement `IN` sesuai qty ACK.
- Return ACK menghasilkan ledger movement `OUT` sesuai qty ACK.
- Replay ACK event yang sama tidak menambah row baru.

### Rollback

- Nonaktifkan call penulisan stock movement pada path ACK di `SyncEngine`.
- Pertahankan `sync_effects` key agar efek lama tetap tidak terduplikasi.

## PR6 - Valuation Parity

### Desain

- Mirror valuation memakai tabel existing `inventory_layers_local` (tanpa migration baru).
- Receive ACK hanya menulis layer jika cost terkonfirmasi (`unit_cost`/`cost`/`price`).
- Return ACK mengonsumsi layer existing dengan urutan FIFO sederhana.

### Acceptance Criteria

- Receive ACK dengan cost valid menambah layer valuation lokal.
- Return ACK mengurangi layer existing sesuai FIFO.
- Replay ACK event yang sama tidak menghasilkan layer ganda.

### Rollback

- Matikan penulisan/consumption layer pada ACK path di `SyncEngine`.
- Data layer existing tidak perlu dihapus massal; biarkan forward-fix via sync replay berikutnya.

## PR7 - Accounting Parity

### Desain

- Ekstraksi journal entries dari ACK payload (nested-safe parser).
- Mirror jurnal ke `audit_events` dengan kategori `accounting_mirror`.
- Tetap dilindungi `sync_effects` agar idempotent saat replay.

### Acceptance Criteria

- ACK dengan journal entries menghasilkan audit mirror lokal.
- Replay ACK tidak menggandakan audit mirror.
- Jika ACK tidak memuat jurnal, mirror accounting tidak dipaksa (no speculation).

### Rollback

- Matikan write audit mirror pada ACK path di `SyncEngine`.
- Jangan ubah parser ACK global; rollback cukup pada bagian efek accounting.

## PR8 - Return Valuation Consistency Hardening

### Desain

- Pada ACK purchase return, stock decrease sudah di-cap oleh stok on-hand aktual.
- Konsumsi valuation layer kini diselaraskan ke `appliedDecrease` (qty yang benar-benar diterapkan ke stok), bukan qty request mentah dari ACK.
- Tujuan: mencegah drift antara `stockOnHand` dan `inventory_layers_local` saat ACK return melebihi stok lokal.

### Acceptance Criteria

- Jika ACK return qty > stok on-hand, perubahan stok tetap non-negatif (existing behavior).
- Konsumsi layer valuation hanya sebesar qty yang benar-benar menurunkan stok.
- Tidak ada over-consume layer pada kasus return berlebih.

### Rollback

- Kembalikan loop konsumsi layer return ke behavior sebelumnya (berbasis requested qty).
- Tidak ada perubahan schema; rollback hanya di logic `SyncEngine`.

## PR9 - Accounting Mirror Non-Blocking Hardening

### Desain

- Accounting mirror tetap dijalankan pada ACK path, namun sekarang bersifat best-effort.
- Jika insert audit mirror gagal, efek utama ACK (stock/purchase/valuation) tetap sukses dan outbox action tidak dipaksa retry.
- Tujuan: mencegah retry tak perlu yang berpotensi men-trigger ulang projection return.

### Acceptance Criteria

- Saat audit mirror DAO gagal, `pushOutbox` tetap sukses dan action terhapus.
- Projection utama ACK tetap diterapkan lengkap.
- Tidak ada perubahan payload contract.

### Rollback

- Kembalikan pemanggilan accounting mirror ke mode blocking seperti sebelumnya.
- Tidak ada perubahan schema/migration.

## Bukti Verifikasi

### Lulus

1. Targeted parity test:

   - Command: `:core-sync:testDebugUnitTest --tests com.umkm.offline.core.sync.SyncEngineTest`
   - Result: `BUILD SUCCESSFUL`

2. Broader module sync test:

   - Command: `:core-sync:testDebugUnitTest`
   - Result: `BUILD SUCCESSFUL`

3. PR8 targeted consistency test:

  - Command: `:core-sync:testDebugUnitTest --tests com.umkm.offline.core.sync.SyncEngineTest`
  - Result: `BUILD SUCCESSFUL`
  - Tambahan test baru: `pushOutbox_returnAckValuationConsumption_isCappedByAppliedStockDecrease`

4. PR9 targeted resilience test:

  - Command: `:core-sync:testDebugUnitTest --tests com.umkm.offline.core.sync.SyncEngineTest`
  - Result: `BUILD SUCCESSFUL`
  - Tambahan test baru: `pushOutbox_receiveAckProjection_stillSucceedsWhenAccountingMirrorFails`

### Status Regression Tambahan

- Command: `:feature-pos:testDebugUnitTest --tests com.umkm.offline.feature.pos.presentation.PosViewModelTest --tests com.umkm.offline.feature.pos.data.PosRepositoryImplTest`
  - Result: `BUILD SUCCESSFUL`
- Command: `:feature-pos:testDebugUnitTest`
  - Result: `BUILD SUCCESSFUL`
- Command: `:app:testDebugUnitTest :feature-pos:testDebugUnitTest`
  - Result: `BUILD SUCCESSFUL`

Catatan: mismatch test sebelumnya berasal dari ekspektasi lama (error code) vs surface perilaku terbaru (human-readable message) dan guard sellability checkout; sudah disejajarkan pada test layer tanpa mengubah guardrail parity ACK di `core-sync`.

## Risiko & Mitigasi

- Risiko: perbedaan shape ACK cost/journal antar endpoint.
  - Mitigasi: parser defensif terhadap field opsional dan nested map/list.
- Risiko: replay multiple batch menulis efek berulang.
  - Mitigasi: `sync_effects` key + deterministic id untuk efek turunan.
- Risiko: skema migration konflik dengan test legacy.
  - Mitigasi: PR6/PR7 memakai tabel existing, tanpa migration baru.

## Rekomendasi Lanjutan

1. Pisahkan triage untuk 7 failing tests di `feature-pos` sebelum full-regression gate release.
2. Lanjut ke PR8 dengan guardrail yang sama: ACK-only, idempotent, small-scoped patch.