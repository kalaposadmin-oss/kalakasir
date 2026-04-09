<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SubscriptionLifecycleCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_lifecycle_command_applies_due_pending_plan_for_target_tenant(): void
    {
        $targetTenant = Tenant::query()->create([
            'name' => 'Tenant Lifecycle Target',
            'timezone' => 'UTC',
            'plan' => 'S',
        ]);
        $otherTenant = Tenant::query()->create([
            'name' => 'Tenant Lifecycle Other',
            'timezone' => 'UTC',
            'plan' => 'S',
        ]);

        $targetSubscriptionId = $this->seedCurrentSubscription($targetTenant->id, [
            'plan' => 'S',
            'status' => 'active',
            'pending_plan' => 'M',
            'pending_change_mode' => 'scheduled',
            'pending_change_effective_at' => now('UTC')->subMinute(),
            'pending_change_reason' => 'Upgrade terjadwal.',
        ]);

        $otherSubscriptionId = $this->seedCurrentSubscription($otherTenant->id, [
            'plan' => 'S',
            'status' => 'active',
            'pending_plan' => 'P',
            'pending_change_mode' => 'scheduled',
            'pending_change_effective_at' => now('UTC')->subMinute(),
            'pending_change_reason' => 'Harus tetap tidak berubah karena scope tenant.',
        ]);

        $this->artisan('subscriptions:enforce-lifecycle', [
            '--tenant_id' => $targetTenant->id,
        ])->expectsOutputToContain('Lifecycle enforcement selesai.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('subscriptions', [
            'id' => $targetSubscriptionId,
            'plan' => 'M',
            'pending_plan' => null,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('tenants', [
            'id' => $targetTenant->id,
            'plan' => 'M',
        ]);

        $this->assertDatabaseHas('subscriptions', [
            'id' => $otherSubscriptionId,
            'plan' => 'S',
            'pending_plan' => 'P',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('tenants', [
            'id' => $otherTenant->id,
            'plan' => 'S',
        ]);
    }

    public function test_lifecycle_command_suspends_past_due_subscription_after_grace_period(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Lifecycle Suspended',
            'timezone' => 'UTC',
            'plan' => 'P',
        ]);

        $subscriptionId = $this->seedCurrentSubscription($tenant->id, [
            'plan' => 'P',
            'status' => 'past_due',
            'grace_ends_at' => now('UTC')->subMinute(),
        ]);

        DB::table('invoices')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'number' => 'INV-LIFECYCLE-001',
            'period_start' => now('UTC')->subMonth()->toDateString(),
            'period_end' => now('UTC')->toDateString(),
            'amount_due' => 100000,
            'amount_paid' => 0,
            'status' => 'unpaid',
            'issued_at' => now('UTC')->subDays(5),
            'due_at' => now('UTC')->subDay(),
            'created_at' => now('UTC')->subDays(5),
            'updated_at' => now('UTC')->subDays(5),
        ]);

        $this->artisan('subscriptions:enforce-lifecycle', [
            '--tenant_id' => $tenant->id,
        ])->expectsOutputToContain('Lifecycle enforcement selesai.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscriptionId,
            'status' => 'suspended',
            'plan' => 'P',
        ]);
        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'plan' => 'S',
        ]);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function seedCurrentSubscription(string $tenantId, array $overrides): string
    {
        $id = (string) Str::uuid();
        $base = [
            'id' => $id,
            'tenant_id' => $tenantId,
            'plan' => 'S',
            'status' => 'active',
            'started_at' => now('UTC')->subMonth(),
            'expires_at' => now('UTC')->addMonth(),
            'current_period_start' => now('UTC')->subMonth(),
            'current_period_end' => now('UTC')->addMonth(),
            'trial_ends_at' => null,
            'grace_ends_at' => null,
            'is_current' => true,
            'pending_plan' => null,
            'pending_change_mode' => null,
            'pending_change_effective_at' => null,
            'pending_change_reason' => null,
            'pending_change_set_by' => null,
            'created_at' => now('UTC')->subMonth(),
            'updated_at' => now('UTC')->subMinute(),
        ];

        DB::table('subscriptions')->insert(array_merge($base, $overrides));

        return $id;
    }
}