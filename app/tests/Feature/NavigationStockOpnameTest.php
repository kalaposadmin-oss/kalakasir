<?php

namespace Tests\Feature;

use Tests\TestCase;

class NavigationStockOpnameTest extends TestCase
{
    public function test_stock_opname_menu_route_is_correct(): void
    {
        $stockOpname = config('navigation.items.stock_opname');
        $this->assertNotNull($stockOpname);
        $this->assertSame('/pos/inventory/stock-opname', $stockOpname['route']);
        $this->assertSame('inventory.stock_opname', $stockOpname['feature']);

        $stockAdjustment = config('navigation.items.stock_adjustment');
        $this->assertNotNull($stockAdjustment);
        $this->assertSame('/pos/inventory/stock-adjustment', $stockAdjustment['route']);
        $this->assertSame('inventory.adjustment', $stockAdjustment['feature']);
    }
}
