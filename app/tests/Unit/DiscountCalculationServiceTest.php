<?php

namespace Tests\Unit;

use App\Services\DiscountCalculationService;
use Tests\TestCase;

class DiscountCalculationServiceTest extends TestCase
{
    public function test_it_calculates_item_and_transaction_discounts_with_simple_stacking(): void
    {
        $service = new DiscountCalculationService();

        $result = $service->calculate([
            'items' => [
                [
                    'product_id' => 'product-a',
                    'qty' => 2,
                    'price' => 10000,
                    'discounts' => [
                        ['type' => 'percent', 'value' => 10, 'label' => 'Promo Item 10%'],
                        ['type' => 'fixed', 'value' => 500, 'label' => 'Potongan Item'],
                    ],
                ],
            ],
            'transaction_discounts' => [
                ['type' => 'percent', 'value' => 5, 'label' => 'Promo Transaksi 5%'],
                ['type' => 'fixed', 'value' => 1000, 'label' => 'Potongan Nota'],
            ],
        ]);

        $this->assertSame(17500.0, $result['subtotal']);
        $this->assertSame(2500.0, $result['item_discount_total']);
        $this->assertSame(1875.0, $result['transaction_discount_total']);
        $this->assertSame(4375.0, $result['total_discount']);
        $this->assertCount(2, $result['items'][0]['discount_breakdown']);
        $this->assertSame(2000.0, $result['items'][0]['discount_breakdown'][0]['applied_value']);
        $this->assertSame(500.0, $result['items'][0]['discount_breakdown'][1]['applied_value']);
        $this->assertCount(2, $result['transaction_discounts']);
        $this->assertSame(875.0, $result['transaction_discounts'][0]['applied_value']);
        $this->assertSame(1000.0, $result['transaction_discounts'][1]['applied_value']);
    }

    public function test_transaction_discount_base_excludes_fee_lines(): void
    {
        $service = new DiscountCalculationService();

        $result = $service->calculate([
            'items' => [
                [
                    'product_id' => 'product-a',
                    'qty' => 1,
                    'price' => 10000,
                    'line_type' => 'product',
                ],
                [
                    'product_id' => 'fee-a',
                    'qty' => 1,
                    'price' => 2000,
                    'line_type' => 'fee',
                ],
            ],
            'transaction_discounts' => [
                ['type' => 'percent', 'value' => 10, 'label' => 'Promo'],
            ],
        ]);

        $this->assertSame(12000.0, $result['subtotal']);
        $this->assertSame(10000.0, $result['transaction_base']);
        $this->assertSame(1000.0, $result['transaction_discount_total']);
    }
}
