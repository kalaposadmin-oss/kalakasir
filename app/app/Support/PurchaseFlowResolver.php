<?php

namespace App\Support;

final class PurchaseFlowResolver
{
    public function resolveMode(?string $purchaseMode, ?string $tenantPurchaseMode = null): string
    {
        $normalizedPurchaseMode = trim((string) $purchaseMode);
        if ($normalizedPurchaseMode !== '') {
            return PurchaseMode::normalize($normalizedPurchaseMode);
        }

        return PurchaseMode::normalize($tenantPurchaseMode ?? PurchaseMode::SIMPLE);
    }

    public function canApprove(string $status, ?string $purchaseMode = null, ?string $tenantPurchaseMode = null): bool
    {
        return PurchaseStatus::normalize($status) === PurchaseStatus::SUBMITTED
            && $this->resolveMode($purchaseMode, $tenantPurchaseMode) === PurchaseMode::CONTROLLED;
    }

    public function canReceive(string $status, ?string $purchaseMode = null, ?string $tenantPurchaseMode = null): bool
    {
        $normalizedStatus = PurchaseStatus::normalize($status);
        $resolvedMode = $this->resolveMode($purchaseMode, $tenantPurchaseMode);

        if ($resolvedMode === PurchaseMode::PO_SHORT) {
            return in_array($normalizedStatus, [PurchaseStatus::SUBMITTED, PurchaseStatus::PARTIALLY_RECEIVED], true);
        }

        if ($resolvedMode === PurchaseMode::CONTROLLED) {
            return in_array($normalizedStatus, [PurchaseStatus::APPROVED, PurchaseStatus::PARTIALLY_RECEIVED], true);
        }

        return false;
    }

    public function canClose(string $status, ?string $purchaseMode = null, ?string $tenantPurchaseMode = null): bool
    {
        $normalizedStatus = PurchaseStatus::normalize($status);

        return in_array($normalizedStatus, [PurchaseStatus::PARTIALLY_RECEIVED, PurchaseStatus::RECEIVED], true);
    }

    public function receiveBlockMessage(string $status, ?string $purchaseMode = null, ?string $tenantPurchaseMode = null): string
    {
        $normalizedStatus = PurchaseStatus::normalize($status);
        $resolvedMode = $this->resolveMode($purchaseMode, $tenantPurchaseMode);

        if (in_array($normalizedStatus, [PurchaseStatus::RECEIVED, PurchaseStatus::CLOSED, PurchaseStatus::CANCELLED], true)) {
            return 'PO sudah selesai atau dibatalkan, tidak bisa menerima barang lagi.';
        }

        if ($resolvedMode === PurchaseMode::SIMPLE) {
            return 'Pembelian langsung tidak memakai alur penerimaan barang.';
        }

        if ($resolvedMode === PurchaseMode::PO_SHORT && $normalizedStatus === PurchaseStatus::DRAFT) {
            return 'PO harus disimpan dulu sebelum menerima barang.';
        }

        if ($resolvedMode === PurchaseMode::CONTROLLED && $normalizedStatus === PurchaseStatus::SUBMITTED) {
            return 'PO harus disetujui dulu sebelum menerima barang.';
        }

        if ($resolvedMode === PurchaseMode::PO_SHORT && $normalizedStatus === PurchaseStatus::SUBMITTED) {
            return 'PO singkat siap diterima.';
        }

        return 'Status PO belum bisa dipakai untuk penerimaan barang.';
    }
}