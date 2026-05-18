<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\OnlineOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BootsApiTenantUser;
use Tests\Concerns\BuildsCheckoutRequestFixture;
use Tests\TestCase;

class OnlineOrderCustomerLinkTest extends TestCase
{
    use BootsApiTenantUser;
    use BuildsCheckoutRequestFixture;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $this->resetApiClock();

        parent::tearDown();
    }

    public function test_permanent_customer_link_prefills_checkout_and_keeps_the_customer_linked(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'online-customer-link@example.test', 'Admin Link Pelanggan');
        $tenant->forceFill(['plan' => 'P'])->save();
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $tenant->forceFill(['default_location_id' => $fixture['location_id']])->save();
        $tenant->refresh();

        $customer = Customer::query()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id' => $tenant->id,
            'name' => 'Theo',
            'phone' => '0821 3672 6327',
            'address' => 'Jl. Mawar 1',
            'created_at' => now('UTC'),
        ]);

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/customers/'.$customer->id.'/order-link');

        $response->assertOk()
            ->assertJsonPath('permanent', true)
            ->assertJsonPath('expires_at', null)
            ->assertJsonPath('customer.id', $customer->id)
            ->assertJsonPath('customer.name', 'Theo')
            ->assertJsonPath('customer.phone', '0821 3672 6327');

        $link = (string) $response->json('order_link');
        $this->assertStringNotContainsString($customer->id, $link);
        $this->assertStringNotContainsString('expires=', $link);
        $this->assertStringNotContainsString('signature=', $link);
        $this->assertStringContainsString('/order/theo-', $link);
        $this->assertMatchesRegularExpression('~/order/theo-[A-Za-z0-9]{20}\z~', parse_url($link, PHP_URL_PATH) ?: '');

        $sameLinkResponse = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/customers/'.$customer->id.'/order-link');

        $sameLinkResponse->assertOk()
            ->assertJsonPath('permanent', true)
            ->assertJsonPath('regenerated', false);

        $this->assertSame($link, (string) $sameLinkResponse->json('order_link'));
        $this->assertDatabaseMissing('customers', [
            'id' => $customer->id,
            'order_link_token_encrypted' => null,
        ]);

        $this->get($link)
            ->assertRedirect('/order');

        $this->get('/order/review')
            ->assertOk()
            ->assertSee('Theo')
            ->assertSee('0821 3672 6327')
            ->assertSee('Jl. Mawar 1');

        $this->get('/order/reset-customer')
            ->assertRedirect('/order');

        $this->get('/order/review')
            ->assertOk()
            ->assertDontSee('Identitas pelanggan lama sudah dipulihkan otomatis dari link admin.')
            ->assertDontSee('Theo')
            ->assertDontSee('0821 3672 6327');

        $submitResponse = $this->post('/order/submit', [
            'customer_name' => 'Theo',
            'customer_phone' => '0821 3672 6327',
            'fulfillment_type' => 'delivery',
            'address' => 'Jl. Mawar 1',
            'customer_note' => 'Repeat order',
            'items' => json_encode([
                [
                    'product_id' => $fixture['product_id'],
                    'qty' => 2,
                ],
            ]),
        ]);

        $submitResponse->assertRedirect();

        /** @var OnlineOrder $order */
        $order = OnlineOrder::query()
            ->where('tenant_id', $tenant->id)
            ->orderByDesc('created_at')
            ->first();

        $this->assertNotNull($order);
        $this->assertSame((string) $customer->id, (string) $order->customer_id);
        $this->assertSame('Theo', $order->customer_name);
        $this->assertSame('0821 3672 6327', $order->customer_phone);
        $this->assertDatabaseHas('online_orders', [
            'id' => $order->id,
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
        ]);

        $this->withHeaders($this->tenantHeaders($tenant->id))
            ->putJson('/api/v1/customers/'.$customer->id, [
                'name' => 'Theo Updated',
                'phone' => '0812 3456 7890',
                'address' => 'Jl. Melati 2',
            ])
            ->assertOk()
            ->assertJsonPath('customer.name', 'Theo Updated')
            ->assertJsonPath('customer.phone', '0812 3456 7890')
            ->assertJsonPath('customer.address', 'Jl. Melati 2');
    }

    public function test_regenerating_customer_link_revokes_the_old_link(): void
    {
        [$tenant] = $this->bootApiActor('admin', 'online-customer-link-rotate@example.test', 'Admin Link Rotate');
        $tenant->forceFill(['plan' => 'P'])->save();

        $customer = Customer::query()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id' => $tenant->id,
            'name' => 'Rina',
            'phone' => '0812 9999 0000',
            'address' => 'Jl. Aman 2',
            'created_at' => now('UTC'),
        ]);

        $first = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/customers/'.$customer->id.'/order-link')
            ->assertOk();

        $oldLink = (string) $first->json('order_link');

        $rotated = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/customers/'.$customer->id.'/order-link/regenerate')
            ->assertOk()
            ->assertJsonPath('permanent', true)
            ->assertJsonPath('regenerated', true);

        $newLink = (string) $rotated->json('order_link');

        $this->assertNotSame($oldLink, $newLink);
        $this->get($oldLink)->assertForbidden();
        $this->get($newLink)->assertRedirect('/order');
    }

    public function test_short_customer_link_uses_name_slug_without_trusting_the_name_as_secret(): void
    {
        [$tenant] = $this->bootApiActor('admin', 'online-customer-link-aldi@example.test', 'Admin Link Aldi');
        $tenant->forceFill(['plan' => 'P'])->save();

        $customer = Customer::query()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id' => $tenant->id,
            'name' => 'Aldi',
            'phone' => '0812 1234 5678',
            'address' => 'Jl. Ringkas 1',
            'created_at' => now('UTC'),
        ]);

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/customers/'.$customer->id.'/order-link')
            ->assertOk();

        $path = parse_url((string) $response->json('order_link'), PHP_URL_PATH) ?: '';

        $this->assertMatchesRegularExpression('~\A/order/aldi-[A-Za-z0-9]{20}\z~', $path);
        $this->get('/order/aldi')->assertForbidden();
        $this->get($path)->assertRedirect('/order');
    }
}
