# APP-COMPILE-BLOCKER-001 - GitHub Issue Template

## Context
Compile blocker pada app module Android terdeteksi setelah workspace drift. Issue ini dipisah dari scope FIFO untuk menjaga kejujuran status parity.

## Problem
- Unresolved references muncul lintas area app module.
- Build runtime app tidak bisa dilanjutkan untuk verifikasi FIFO E2E.

## Reproduction
1. Run `:app:compileDebugKotlin`
2. Run `:app:assembleDebug`
3. Lihat compile error unresolved references pada file app module

## Impact
- Runtime parity verification tertahan.
- FIFO bucket belum bisa dipromosikan ke FULL.

## Scope
- Root-cause compile issue lintas file app module
- Patch minimal restore buildability
- Verifikasi no-regression FIFO tests

## Acceptance Criteria
- [ ] assembleDebug SUCCESS
- [ ] MainActivity compile resolved
- [ ] No regression on FIFO tests

## Parity Status Impact
FIFO bucket cannot be marked FULL until this blocker is resolved.

## Related Docs
- Parity Board: docs/apk-parity-test-scenarios-sprint1-2026-03-18.md
- Traceability Matrix: docs/apk-traceability-matrix-2026-03-18.md
- Blocker Doc: docs/app-compile-blocker-001.md

## Unblock Criteria
This issue is considered resolved only when:
- App module builds successfully
- FIFO E2E flow (checkout + void) can be executed from UI
- Outbox payload contains fifo_allocations and fifo_reversal_allocations correctly
