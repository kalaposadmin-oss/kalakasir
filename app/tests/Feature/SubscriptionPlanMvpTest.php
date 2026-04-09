<?php

namespace Tests\Feature;

use App\Events\SubscriptionPlanChanged;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\BootsApiTenantUser;
use Tests\TestCase;

class SubscriptionPlanMvpTest extends TestCase
{
    use BootsApiTenantUser, RefreshDatabase;

    private Tenant $tenant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->tenant, $this->user] = $this->bootApiActor('admin', 'admin-subscription@example.com', 'Admin Subscription');
    }

    protected function tearDown(): void
    {
        $this->resetApiClock();

        parent::tearDown();
    }

    public function test_default_plan_is_s_by_default(): void
    {
        $response = $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->getJson('/api/v1/subscription/current');

        $response->assertStatus(200)
            ->assertJson([
                'plan' => 'S',
            ]);
    }

    public function test_plan_m_unlocks_m_level_feature(): void
    {
        \DB::table('tenants')->where('id', $this->tenant->id)->update(['plan' => 'M']);

        $response = $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->getJson('/api/v1/subscription/entitlements?search=search.global');

        $response->assertStatus(200)
            ->assertJsonPath('plan', 'M')
            ->assertJsonPath('entitlements.0.key', 'search.global')
            ->assertJsonPath('entitlements.0.allowed', true);
    }

    public function test_plan_p_unlocks_p_level_feature(): void
    {
        \DB::table('tenants')->where('id', $this->tenant->id)->update(['plan' => 'P']);

        $response = $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->getJson('/api/v1/subscription/entitlements?search=reports.export_csv');

        $response->assertStatus(200)
            ->assertJsonPath('plan', 'P')
            ->assertJsonPath('entitlements.0.key', 'reports.export_csv')
            ->assertJsonPath('entitlements.0.allowed', true);
    }

    public function test_admin_can_patch_plan_with_valid_reason_and_audit_is_written(): void
    {
        Event::fake([SubscriptionPlanChanged::class]);

        $response = $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->patchJson('/api/v1/subscription/plan', [
                'plan' => 'M',
                'reason' => 'Upgrade untuk kebutuhan operasional.',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('plan', 'M');

        $this->assertDatabaseHas('subscription_audit_logs', [
            'tenant_id' => $this->tenant->id,
            'actor_id' => $this->user->id,
            'old_plan' => 'S',
            'new_plan' => 'M',
        ]);

        Event::assertDispatched(SubscriptionPlanChanged::class, function (SubscriptionPlanChanged $event): bool {
            return $event->tenantId === $this->tenant->id
                && $event->oldPlan === 'S'
                && $event->newPlan === 'M'
                && $event->actorId === $this->user->id;
        });
    }

    public function test_patch_plan_requires_reason_minimum_length(): void
    {
        $response = $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->patchJson('/api/v1/subscription/plan', [
                'plan' => 'M',
                'reason' => 'too short',
            ]);

        $response->assertStatus(422);
    }

    public function test_cashier_cannot_patch_plan(): void
    {
        [$tenant, $user] = $this->bootApiActor('cashier', 'cashier-subscription@example.com', 'Cashier Subscription');

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->patchJson('/api/v1/subscription/plan', [
                'plan' => 'M',
                'reason' => 'Attempted upgrade by cashier role.',
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('code', 'FORBIDDEN');
    }
}
