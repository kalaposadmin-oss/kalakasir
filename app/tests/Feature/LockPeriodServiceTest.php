<?php

namespace Tests\Feature;

use App\Exceptions\LockPeriodException;
use App\Models\LockPeriod;
use App\Models\Tenant;
use App\Services\LockPeriodService;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LockPeriodServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::query()->create([
            'name' => 'Tenant A',
            'timezone' => 'UTC',
        ]);

        TenantContext::set($tenant);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();

        parent::tearDown();
    }

    public function test_it_throws_when_date_is_inside_locked_period(): void
    {
        LockPeriod::query()->create([
            'start_date' => '2026-02-01',
            'end_date' => '2026-02-29',
            'is_locked' => true,
        ]);

        $this->expectException(LockPeriodException::class);

        (new LockPeriodService())->ensureNotLocked(
            CarbonImmutable::parse('2026-02-10', 'UTC'),
            TenantContext::id()
        );
    }

    public function test_it_allows_date_outside_locked_period(): void
    {
        LockPeriod::query()->create([
            'start_date' => '2026-02-01',
            'end_date' => '2026-02-10',
            'is_locked' => true,
        ]);

        (new LockPeriodService())->ensureNotLocked(
            CarbonImmutable::parse('2026-02-11', 'UTC'),
            TenantContext::id()
        );

        $this->assertTrue(true);
    }
}
