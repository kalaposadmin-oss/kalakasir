<?php

namespace Tests\Unit;

use App\Models\OnlineOrder;
use App\Models\PaymentSetting;
use App\Services\WhatsAppMessageService;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class WhatsAppMessageServiceTest extends TestCase
{
    public function test_customer_payment_message_includes_qris_image_url_when_available(): void
    {
        $order = new OnlineOrder([
            'order_code' => 'LV-95451',
            'customer_name' => 'Theo',
            'subtotal' => 30000,
            'shipping_provider' => 'Shopee Sent',
            'shipping_cost' => 10000,
            'shipping_discount' => 5000,
            'extra_fee' => 0,
            'grand_total' => 35000,
        ]);
        $order->setRelation('items', new Collection([
            (object) [
                'product_name' => 'Roti A',
                'qty' => 3,
                'subtotal' => 30000,
            ],
        ]));

        $paymentSetting = new PaymentSetting([
            'bank_name' => 'BCA',
            'bank_account_number' => '0300990099',
            'bank_account_name' => 'Tmak',
            'qris_image_url' => 'https://example.test/qris-levia.png',
        ]);

        $message = (new WhatsAppMessageService())->buildCustomerPaymentMessage($order, $paymentSetting);

        $this->assertStringContainsString('Bank: BCA', $message);
        $this->assertStringContainsString('QRIS: https://example.test/qris-levia.png', $message);
    }
}
