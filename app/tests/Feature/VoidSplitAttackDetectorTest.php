<?php

namespace Tests\Feature;

use App\Jobs\DetectVoidSplitAttackJob;
use App\Services\AnomalyService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\Concerns\BuildsCheckoutRequestFixture;
use Tests\TestCase;

class VoidSplitAttackDetectorTest extends TestCase
{
    use BootsApiTenantUser, BuildsCheckoutRequestFixture, RefreshDatabase;

    protected function tearDown(): void
    {
        $this->resetApiClock();

        parent::tearDown();
    }

    public function test_detects_small_void_split_attack_by_single_actor(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin-split-attack@example.com', 'Admin Split Attack');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);

        config([
            'services.anomaly.void_split_window_minutes' => 60,
            'services.anomaly.void_split_small_amount_limit' => 50000,
            'services.anomaly.void_split_actor_count_threshold' => 8,
            'services.anomaly.void_split_tenant_min_floor' => 25,
        ]);

        $now = CarbonImmutable::now('UTC');

        $rows = [];
        for ($i = 1; $i <= 12; $i++) {
            $rows[] = [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'branch_id' => $fixture['branch_id'],
                'shift_id' => $fixture['shift_id'],
                'customer_id' => null,
                'subtotal' => 10000,
                'discount' => 0,
                'tax' => 0,
                'total' => 10000,
                'status' => 'voided',
                'idempotency_key' => (string) Str::uuid(),
                'created_by' => $user->id,
                'voided_by' => $user->id,
                'void_reason' => 'test split',
                'voided_at' => $now->subMinutes(20),
                'created_at' => $now->subMinutes(30),
                'transaction_date' => $now->toDateString(),
            ];
        }

        \DB::table('sales')->insert($rows);

        $job = new DetectVoidSplitAttackJob();
        $job->handle(app(AnomalyService::class));

        $this->assertDatabaseHas('anomalies', [
            'tenant_id' => $tenant->id,
            'severity' => 'high',
            'type' => 'void_split_attack_actor',
        ]);
    }
}
