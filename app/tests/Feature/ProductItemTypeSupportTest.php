<?php

namespace Tests\Feature;

use App\Services\ProductItemTypeMapper;
use App\Services\ProductItemTypeValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductItemTypeSupportTest extends TestCase
{
    use RefreshDatabase;

    public function test_mapper_resolves_item_type_from_mode_when_missing(): void
    {
        $this->assertSame('raw_material', ProductItemTypeMapper::resolve(null, 'add_on'));
        $this->assertSame('finished_good', ProductItemTypeMapper::resolve(null, 'bom'));
        $this->assertSame('service', ProductItemTypeMapper::resolve('service', 'default'));
    }

    public function test_validator_rejects_invalid_mode_and_item_type_combination(): void
    {
        $this->expectException(ValidationException::class);

        ProductItemTypeValidator::assertModeItemTypeValid('add_on', 'finished_good');
    }

    public function test_validator_rejects_service_with_tracked_stock(): void
    {
        $this->expectException(ValidationException::class);

        ProductItemTypeValidator::assertServiceTrackStockValid('service', true);
    }

    public function test_validator_rejects_invalid_batch_production_setup(): void
    {
        $this->expectException(ValidationException::class);

        ProductItemTypeValidator::assertProductionModeValid('batch_production', 'raw_material', true);
    }
}