# Release Gate Runbook - Purchase Parity APK (PR1-PR10)

## Tujuan
Menetapkan gate final sebelum menyatakan parity modul pembelian APK siap release, dengan kriteria pass/fail yang jelas dan dapat dieksekusi cepat oleh QA, Ops, dan reviewer.

## Prasyarat
- Branch release candidate sudah freeze untuk scope purchase parity (tanpa perubahan domain baru).
- Environment test siap:
  - Backend endpoint sinkronisasi aktif.
  - Build tool Android siap (Gradle + SDK).
  - Data tenant uji tersedia untuk create/receive/return.
- Tidak ada issue blocker severity P0/P1 yang open untuk jalur purchase sync.

## Command Checklist
Jalankan dari folder mobile-android-umkm-offline.

1. Core parity sync gate
- ./gradlew :core-sync:testDebugUnitTest --tests com.umkm.offline.core.sync.SyncEngineTest --no-daemon --console=plain

2. Full core-sync unit suite
- ./gradlew :core-sync:testDebugUnitTest --no-daemon --console=plain

3. Feature-pos guardrail suite
- ./gradlew :feature-pos:testDebugUnitTest --no-daemon --console=plain

4. App + feature-pos integration unit gate
- ./gradlew :app:testDebugUnitTest :feature-pos:testDebugUnitTest --no-daemon --console=plain

5. Compile/build gate
- ./gradlew :app:assembleDebug --no-daemon --console=plain

Catatan eksekusi:
- Di Windows gunakan gradlew.bat.
- Jika salah satu command gagal, hentikan gate dan masuk jalur fail.

## Smoke Test Checklist (Manual Wajib)
Gunakan 1 tenant uji, 1 produk stok aktif, dan 1 purchase flow lengkap.

1. Create purchase
- Buat PO/purchase draft dari APK.
- Verifikasi outbox action terbentuk.
- Trigger sync.
- Ekspektasi: status sinkron sukses, tanpa error blocking.

2. Receive purchase (ACK success)
- Lakukan receive qty valid.
- Trigger sync sampai ACK sukses.
- Verifikasi:
  - stok produk naik sesuai ACK,
  - ledger movement IN tercatat 1x,
  - valuation layer receive bertambah,
  - accounting mirror tercatat bila jurnal tersedia.

3. Return purchase (ACK success)
- Lakukan return qty valid.
- Trigger sync sampai ACK sukses.
- Verifikasi:
  - stok produk turun sesuai ACK (non-negatif),
  - ledger movement OUT tercatat 1x,
  - valuation layer terpakai sesuai applied decrease,
  - accounting mirror tercatat bila jurnal tersedia.

4. ACK explicit failure path
- Simulasikan ACK payload explicit failure (status failed/error atau success=false).
- Verifikasi:
  - action menjadi FAILED + retryable,
  - tidak ada projection lokal baru (stock/ledger/layer tidak berubah),
  - tidak ada delete action outbox pada attempt gagal.

5. Duplicate/replay safety
- Replay action/ACK yang sama (idempotency key/effect key sama).
- Verifikasi:
  - tidak ada double-apply stok,
  - tidak ada duplikasi ledger/layer/mirror,
  - state akhir tetap konsisten.

## Pass Criteria
Semua poin di bawah wajib terpenuhi:
- Semua command checklist sukses (BUILD SUCCESSFUL).
- Smoke test create/receive/return sukses sesuai ekspektasi.
- ACK success menghasilkan projection tepat 1x.
- ACK explicit failure tidak menghasilkan projection lokal.
- Replay/duplicate tidak membuat efek ganda.
- Tidak ada blocker P0/P1 baru dari hasil gate.

## Fail Criteria
Satu saja terpenuhi maka gate FAIL:
- Salah satu command unit/build gagal.
- Smoke test mandatory tidak lulus.
- Ditemukan drift state stock vs ledger vs layer.
- ACK explicit failure tetap memodifikasi data lokal.
- Replay menghasilkan duplikasi efek.
- Error kritis baru (crash/data corruption/security) muncul pada jalur purchase sync.

## Rollback Trigger
Lakukan rollback release candidate jika salah satu terjadi:
- Regression pada jalur purchase receive/return di environment gate.
- Data inconsistency terkonfirmasi pada stock/ledger/valuation setelah sync.
- Retry storm atau fail loop pada outbox purchase action.
- ACK failure handling tidak aman (projection tetap jalan saat explicit failure).

Langkah rollback minimum:
- Stop rollout RC.
- Revert commit terakhir yang memicu regression.
- Jalankan ulang command checklist + smoke test mandatory sebelum re-promote RC.

## Evidence Checklist
Kumpulkan artefak berikut untuk sign-off:

1. Command output
- Log BUILD SUCCESSFUL untuk seluruh command checklist.

2. Test report
- Ringkasan hasil test untuk core-sync, feature-pos, app+feature-pos.

3. Smoke evidence
- Screenshot/rekaman langkah create, receive, return.
- Bukti state sebelum/sesudah (stok, ledger row, layer row, mirror event bila ada).

4. Failure-path evidence
- Bukti ACK explicit failure tidak mengubah projection lokal.
- Bukti action status FAILED + retry path aktif.

5. Replay safety evidence
- Bukti tidak ada duplicate row/effect pada replay.

6. Sign-off
- QA: nama, tanggal, hasil pass/fail.
- Ops: nama, tanggal, hasil pass/fail.
- Reviewer/Tech Lead: nama, tanggal, keputusan release.
