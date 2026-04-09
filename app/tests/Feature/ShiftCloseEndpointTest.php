<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Models\Payment;
use App\Models\Shift;
use App\Models\User;
use App\Models\Branch;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftCloseEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function seedTenantContext()
    {
        $tenant = \App\Models\Tenant::create([
            'name' => 'Test Tenant',
            'timezone' => 'UTC',
        ]);

        TenantContext::set($tenant);
        return $tenant;
    }

    public function test_close_shift_endpoint_and_summary()
    {
        $tenant = $this->seedTenantContext();

        $branch = Branch::create(['tenant_id' => $tenant->id, 'name' => 'Main']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $this->actingAs($user, 'sanctum');

        // open a shift
        $shift = Shift::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'opened_at' => now(),
            'opening_cash' => 10000,
            'closing_cash' => 0,
            'system_cash_total' => 0,
            'difference' => 0,
            'status' => 'open',
        ]);

        // no sales/payments created: shift will have zero expected cash

        // call close endpoint
        $response = $this->postJson('/api/v1/shifts/close', [
            'shift_id' => $shift->id,
            'closing_cash' => 60000,
            'close_note' => 'Test close',
        ], ['X-Tenant-Id' => $tenant->id]);

        $response->assertStatus(200)->assertJsonStructure(['status', 'difference']);

        $shift->refresh();
        $this->assertEquals('closed', $shift->status);
        $this->assertEquals(60000, (int) $shift->closing_cash);

        // fetch close summary
        $summary = $this->getJson('/api/v1/shifts/close-summary?shift_id='.$shift->id, ['X-Tenant-Id' => $tenant->id]);
        $summary->assertStatus(200)->assertJsonPath('shift.id', $shift->id);
        $summary->assertJsonPath('shift.difference', 60000);
    }
}
