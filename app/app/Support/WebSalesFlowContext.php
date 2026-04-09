<?php

namespace App\Support;

use Illuminate\Http\Request;

class WebSalesFlowContext
{
    public function __construct(private readonly WebEntityFlowContext $entityFlow)
    {
    }

    public function resolvePortal(Request $request, string $default = 'pos'): string
    {
        return $this->entityFlow->resolvePortal($request, $default);
    }

    public function resolveBackUrl(Request $request, string $portal, string $fallback, array $allowedPrefixes = []): string
    {
        return $this->entityFlow->resolveBackUrl($request, $portal, $fallback, $allowedPrefixes);
    }

    public function salesDetailContext(Request $request, string $portal, string $saleId): array
    {
        $currentPath = '/'.$portal.'/sales/'.$saleId;
        $canVoidRefund = $this->canVoidRefund($portal);

        return [
            'portal' => $portal,
            'currentPath' => $currentPath,
            'backUrl' => $this->resolveBackUrl(
                $request,
                $portal,
                $portal === 'backoffice' ? '/backoffice/reports/sales' : '/pos/history',
                $portal === 'backoffice'
                    ? ['/backoffice/reports/sales', '/backoffice/sales/']
                    : ['/pos/history', '/pos/sales/', '/pos/sales/'.$saleId.'/void-refund', '/pos/sales/'.$saleId.'/receipt']
            ),
            'showVoidRefundAction' => $canVoidRefund,
            'voidRefundUrl' => $canVoidRefund ? $currentPath.'/void-refund' : null,
            'receiptUrl' => $portal === 'pos' ? $currentPath.'/receipt' : null,
        ];
    }

    public function salesVoidRefundContext(Request $request, string $saleId): array
    {
        $detailUrl = '/pos/sales/'.$saleId;

        return [
            'portal' => 'pos',
            'currentPath' => $detailUrl.'/void-refund',
            'backUrl' => $this->resolveBackUrl(
                $request,
                'pos',
                $detailUrl,
                ['/pos/history', '/pos/sales/', '/pos/payment-method', '/pos/checkout']
            ),
            'detailUrl' => $detailUrl,
            'historyUrl' => '/pos/history',
        ];
    }

    public function paymentMethodContext(Request $request): array
    {
        return [
            'portal' => 'pos',
            'currentPath' => '/pos/payment-method',
            'backUrl' => $this->resolveBackUrl(
                $request,
                'pos',
                '/pos/checkout',
                ['/pos/checkout', '/pos/sales/']
            ),
            'receiptFallbackUrl' => '/pos/receipt-preview',
        ];
    }

    public function salesReceiptContext(Request $request, string $saleId): array
    {
        $detailUrl = $saleId !== '' ? '/pos/sales/'.$saleId : '/pos/history';
        $currentPath = $saleId !== '' ? $detailUrl.'/receipt' : '/pos/receipt-preview';

        return [
            'portal' => 'pos',
            'currentPath' => $currentPath,
            'backUrl' => $this->resolveBackUrl(
                $request,
                'pos',
                $detailUrl,
                ['/pos/payment-method', '/pos/sales/', '/pos/history']
            ),
            'detailUrl' => $detailUrl,
        ];
    }

    public function legacySalesDetailTarget(Request $request, string $saleId): string
    {
        return $this->entityFlow->legacyTarget($request, 'pos', 'sales', $saleId);
    }

    public function legacySalesVoidRefundTarget(Request $request, string $saleId = ''): string
    {
        $portal = $this->resolvePortal($request, 'pos');

        if ($portal === 'backoffice') {
            return '/backoffice/reports/sales';
        }

        if ($saleId !== '') {
            return '/pos/sales/'.$saleId.'/void-refund';
        }

        return '/pos/void-refund';
    }

    public function legacyReceiptPreviewTarget(string $saleId = ''): string
    {
        if ($saleId !== '') {
            return '/pos/sales/'.$saleId.'/receipt';
        }

        return '/pos/receipt-preview';
    }

    public function canVoidRefund(string $portal): bool
    {
        return $portal === 'pos';
    }
}
