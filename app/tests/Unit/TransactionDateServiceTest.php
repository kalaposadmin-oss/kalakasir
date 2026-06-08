<?php

namespace Tests\Unit;

use App\Exceptions\InvalidTransactionDateException;
use App\Models\Tenant;
use App\Services\TransactionDateService;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class TransactionDateServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        TenantContext::clear();
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_resolve_returns_today_when_transaction_date_is_null(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-02-27 10:30:00', 'UTC'));
        TenantContext::set(new Tenant([
            'id' => '11111111-1111-1111-1111-111111111111',
            'name' => 'Tenant A',
            'timezone' => 'UTC',
        ]));

        $resolved = (new TransactionDateService())->resolve(null);

        $this->assertSame('2026-02-27', $resolved->toDateString());
    }

    public function test_resolve_rejects_future_transaction_date(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-02-27 10:30:00', 'UTC'));
        TenantContext::set(new Tenant([
            'id' => '11111111-1111-1111-1111-111111111111',
            'name' => 'Tenant A',
            'timezone' => 'UTC',
        ]));

        $this->expectException(InvalidTransactionDateException::class);

        (new TransactionDateService())->resolve('2026-02-28');
    }

    public function test_resolve_accepts_transaction_date_before_current_month(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-02-27 10:30:00', 'UTC'));
        TenantContext::set(new Tenant([
            'id' => '11111111-1111-1111-1111-111111111111',
            'name' => 'Tenant A',
            'timezone' => 'UTC',
        ]));

        $resolved = (new TransactionDateService())->resolve('2026-01-31');

        $this->assertSame('2026-01-31', $resolved->toDateString());
    }

    public function test_resolve_accepts_transaction_date_in_current_month(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-02-27 10:30:00', 'UTC'));
        TenantContext::set(new Tenant([
            'id' => '11111111-1111-1111-1111-111111111111',
            'name' => 'Tenant A',
            'timezone' => 'UTC',
        ]));

        $resolved = (new TransactionDateService())->resolve('2026-02-01');

        $this->assertSame('2026-02-01', $resolved->toDateString());
    }
}
