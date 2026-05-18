<?php

namespace App\Support;

final class PurchaseStatus
{
    public const DRAFT = 'draft';
    public const SUBMITTED = 'submitted';
    public const APPROVED = 'approved';
    public const PARTIALLY_RECEIVED = 'partially_received';
    public const RECEIVED = 'received';
    public const CLOSED = 'closed';
    public const CANCELLED = 'cancelled';

    private const CREATE_ALIASES = [
        'menunggu' => self::SUBMITTED,
        'pending' => self::SUBMITTED,
    ];

    private const REJECT_ALIASES = [
        'rejected' => self::CANCELLED,
    ];

    public static function all(): array
    {
        return [
            self::DRAFT,
            self::SUBMITTED,
            self::APPROVED,
            self::PARTIALLY_RECEIVED,
            self::RECEIVED,
            self::CLOSED,
            self::CANCELLED,
        ];
    }

    public static function createInputs(): array
    {
        return [
            self::DRAFT,
            self::SUBMITTED,
        ];
    }

    public static function aliases(): array
    {
        return self::CREATE_ALIASES + self::REJECT_ALIASES;
    }

    public static function normalize(?string $status): string
    {
        $value = strtolower(trim((string) $status));

        if ($value === '') {
            return self::DRAFT;
        }

        if (isset(self::CREATE_ALIASES[$value])) {
            return self::CREATE_ALIASES[$value];
        }

        if (isset(self::REJECT_ALIASES[$value])) {
            return self::REJECT_ALIASES[$value];
        }

        return in_array($value, self::all(), true) ? $value : self::DRAFT;
    }

    public static function labels(): array
    {
        return [
            self::DRAFT => 'Draft',
            self::SUBMITTED => 'Siap Diproses',
            self::APPROVED => 'Disetujui',
            self::PARTIALLY_RECEIVED => 'Diterima Sebagian',
            self::RECEIVED => 'Diterima',
            self::CLOSED => 'Ditutup',
            self::CANCELLED => 'Dibatalkan',
        ];
    }

    public static function label(?string $status): string
    {
        $normalized = self::normalize($status);

        return self::labels()[$normalized] ?? self::labels()[self::DRAFT];
    }

    public static function canApprove(string $status, ?string $purchaseMode = null): bool
    {
        return (new PurchaseFlowResolver())->canApprove($status, $purchaseMode);
    }

    public static function canCancel(string $status): bool
    {
        return in_array(self::normalize($status), [self::DRAFT, self::SUBMITTED, self::APPROVED], true);
    }

    public static function canReceive(string $status, ?string $purchaseMode = null): bool
    {
        return (new PurchaseFlowResolver())->canReceive($status, $purchaseMode);
    }

    public static function canClose(string $status, ?string $purchaseMode = null): bool
    {
        return (new PurchaseFlowResolver())->canClose($status, $purchaseMode);
    }

    public static function receiveBlockMessage(string $status, ?string $purchaseMode = null): string
    {
        return (new PurchaseFlowResolver())->receiveBlockMessage($status, $purchaseMode);
    }
}