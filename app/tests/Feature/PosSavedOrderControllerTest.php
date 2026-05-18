<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\TestCase;

class PosSavedOrderControllerTest extends TestCase
{
    use BootsApiTenantUser;
    use RefreshDatabase;

    public function test_update_saved_order_reuses_the_existing_row_and_keeps_item_notes(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'pos-saved-order@example.test', 'Admin POS');

        $createResponse = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/pos/saved-orders', [
                'holder_name' => 'Andi',
                'note' => 'Pisahkan saus.',
                'items' => [[
                    'product_id' => (string) Str::uuid(),
                    'qty' => 1,
                    'discounts' => [],
                    'note' => 'gula sedikit',
                ]],
                'fees' => [],
                'selected_promos' => [],
                'transaction_discounts' => [],
                'total' => 15000,
                'item_count' => 1,
            ]);

        $createResponse->assertStatus(201);

        $savedOrderId = (string) $createResponse->json('saved_order.id');
        $this->assertNotSame('', $savedOrderId);

        $updateResponse = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->putJson('/api/v1/pos/saved-orders/'.$savedOrderId, [
                'holder_name' => 'Andi Revisi',
                'note' => 'Tanpa saus.',
                'items' => [[
                    'product_id' => (string) Str::uuid(),
                    'qty' => 2,
                    'discounts' => [],
                    'note' => 'gula sangat sedikit',
                ]],
                'fees' => [],
                'selected_promos' => [],
                'transaction_discounts' => [],
                'total' => 30000,
                'item_count' => 2,
            ]);

        $updateResponse->assertOk();
        $updateResponse->assertJsonPath('saved_order.id', $savedOrderId);
        $updateResponse->assertJsonPath('saved_order.holder_name', 'Andi Revisi');
        $updateResponse->assertJsonPath('saved_order.note', 'Tanpa saus.');
        $updateResponse->assertJsonPath('saved_order.items.0.note', 'gula sangat sedikit');

        $this->assertSame(1, \DB::table('pos_saved_orders')->where('tenant_id', $tenant->id)->count());
        $this->assertDatabaseHas('pos_saved_orders', [
            'id' => $savedOrderId,
            'tenant_id' => $tenant->id,
            'holder_name' => 'Andi Revisi',
            'note' => 'Tanpa saus.',
            'item_count' => 2,
        ]);
    }
}