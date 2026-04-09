# APP-COMPILE-BLOCKER-001 - Jira Cloud Template

Summary:
- APP-COMPILE-BLOCKER-001: Root-cause unresolved references across app module after workspace drift

Description:
- Compile failure terjadi di app module Android dengan unresolved references tersebar lintas file.
- Issue ini diperlakukan sebagai non-FIFO task terpisah agar evidence parity FIFO Web + repository tetap bersih.

Steps to Reproduce:
1. Jalankan :app:compileDebugKotlin pada project mobile-android-umkm-offline.
2. Observasi unresolved references pada MainActivity.kt dan file terkait.
3. Jalankan :app:assembleDebug dan verifikasi build gagal.

Expected Result:
- App module berhasil compile.
- assembleDebug sukses.
- Simbol governance/screen/extension ter-resolve normal.

Actual Result:
- Compile gagal dengan unresolved references lintas file.
- assembleDebug tertahan.

Scope:
- Root-cause analysis unresolved references di app module.
- Patch minimal untuk restore buildability.
- Re-validate agar tidak ada regresi pada jalur FIFO yang sudah tervalidasi.

Out of Scope:
- Perubahan behavior FIFO tanpa evidence test.
- Refactor besar di luar kebutuhan perbaikan compile.

Acceptance Criteria:
- assembleDebug sukses.
- Simbol governance/screen/extension kembali ter-resolve.
- Tidak ada perubahan fungsional FIFO tanpa evidence test.

Parity Impact:
- FIFO bucket cannot be marked FULL until this blocker is resolved.
- Current validated state tetap: Web FIFO parity PASS, Android repository FIFO tests PASS.

Risk:
- Risiko regressi lintas layar jika patch tidak minimal.
- Risiko masking issue jika hanya fix parsial tanpa verifikasi assembleDebug.

Labels:
- parity-blocker
- android
- compile
- non-fifo

Traceability Link:
- Parity Board: docs/apk-parity-test-scenarios-sprint1-2026-03-18.md
- Traceability Matrix: docs/apk-traceability-matrix-2026-03-18.md
- Blocker Doc: docs/app-compile-blocker-001.md

Unblock Criteria:
- App module builds successfully.
- FIFO E2E flow (checkout + void) can be executed from UI.
- Outbox payload contains fifo_allocations and fifo_reversal_allocations correctly.
