<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\TestCase;

class LockPeriodTenantScopingTest extends TestCase
{
    use RefreshDatabase;
    use BootsApiTenantUser;

    public function test_index_excludes_foreign_tenant_lock_periods(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+lock-period-primary@example.test');
        [$foreignTenant] = $this->bootApiActor('admin', 'admin+lock-period-foreign@example.test');

        $this->actingAs($user, 'sanctum');

        $ownPeriodId = $this->insertLockPeriod($tenant->id, '2026-02-01', '2026-02-29', true);
        $foreignPeriodId = $this->insertLockPeriod($foreignTenant->id, '2026-03-01', '2026-03-31', true);

        $this->getJson('/api/v1/lock-periods', $this->tenantHeaders($tenant->id))
            ->assertOk()
            ->assertJsonMissing(['id' => $foreignPeriodId])
            ->assertJsonPath('periods.0.id', $ownPeriodId);
    }

    public function test_toggle_returns_404_for_foreign_tenant_lock_period(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+lock-period-toggle@example.test');
        [$foreignTenant] = $this->bootApiActor('admin', 'admin+lock-period-toggle-foreign@example.test');

        $this->actingAs($user, 'sanctum');

        $foreignPeriodId = $this->insertLockPeriod($foreignTenant->id, '2026-03-01', '2026-03-31', true);

        $this->patchJson('/api/v1/lock-periods/'.$foreignPeriodId, [
            'is_locked' => false,
        ], $this->tenantHeaders($tenant->id))
            ->assertStatus(404)
            ->assertJsonPath('message', 'Periode lock tidak ditemukan.');

        $this->assertDatabaseHas('lock_periods', [
            'id' => $foreignPeriodId,
            'tenant_id' => $foreignTenant->id,
            'is_locked' => true,
        ]);
    }

    private function insertLockPeriod(string $tenantId, string $startDate, string $endDate, bool $isLocked): string
    {
        $periodId = (string) Str::uuid();

        DB::table('lock_periods')->insert([
            'id' => $periodId,
            'tenant_id' => $tenantId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'is_locked' => $isLocked,
            'locked_by' => null,
            'locked_at' => null,
            'reason' => null,
        ]);

        return $periodId;
    }
}