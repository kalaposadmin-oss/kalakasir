<?php

namespace Tests\Feature;

use App\Jobs\BackfillProductItemTypeJob;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductItemTypeBackfillGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_pos_audit_consistency_displays_item_type_indicators(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Audit',
            'timezone' => 'UTC',
        ]);

        $this->artisan('pos:audit-consistency', ['--tenant_id' => $tenant->id])
            ->expectsOutputToContain('item_type_is_null')
            ->expectsOutputToContain('mode_add_on_not_raw_material')
            ->expectsOutputToContain('mode_bom_not_finished_good')
            ->assertExitCode(0);
    }

    public function test_run_one_batch_creates_checkpoint_for_null_tenant_scope(): void
    {
        $job = new BackfillProductItemTypeJob(null, 100, 1);
        $processed = $job->runOneBatch(null, 100);

        $this->assertSame(0, $processed);
        $this->assertDatabaseHas('product_item_type_backfill_checkpoints', [
            'scope_key' => 'tenant:__NULL__',
            'tenant_id' => null,
        ]);
    }

    public function test_run_one_batch_backfills_mapping_for_add_on_and_bom(): void
    {
        $tenantA = Tenant::query()->create([
            'name' => 'Tenant A',
            'timezone' => 'UTC',
        ]);
        $tenantB = Tenant::query()->create([
            'name' => 'Tenant B',
            'timezone' => 'UTC',
        ]);

        DB::table('products')->insert([
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantA->id,
                'sku' => 'A-ADDON-001',
                'name' => 'Addon A',
                'price' => 1000,
                'hpp_average' => 0,
                'is_active' => true,
                'track_stock' => true,
                'product_mode' => 'add_on',
                'item_type' => null,
                'created_at' => now('UTC')->subSeconds(3),
                'updated_at' => now('UTC')->subSeconds(3),
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantA->id,
                'sku' => 'A-BOM-001',
                'name' => 'BOM A',
                'price' => 2000,
                'hpp_average' => 0,
                'is_active' => true,
                'track_stock' => true,
                'product_mode' => 'bom',
                'item_type' => null,
                'created_at' => now('UTC')->subSeconds(2),
                'updated_at' => now('UTC')->subSeconds(2),
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantB->id,
                'sku' => 'B-DEFAULT-001',
                'name' => 'Default B',
                'price' => 3000,
                'hpp_average' => 0,
                'is_active' => true,
                'track_stock' => true,
                'product_mode' => 'default',
                'item_type' => null,
                'created_at' => now('UTC')->subSecond(),
                'updated_at' => now('UTC')->subSecond(),
            ],
        ]);

        $job = new BackfillProductItemTypeJob(null, 10, 1);
        $processed = $job->runOneBatch(null, 10);

        $this->assertSame(3, $processed);

        $this->assertSame(1, DB::table('products')
            ->where('tenant_id', $tenantA->id)
            ->where('product_mode', 'add_on')
            ->where('item_type', 'raw_material')
            ->count());

        $this->assertSame(1, DB::table('products')
            ->where('tenant_id', $tenantA->id)
            ->where('product_mode', 'bom')
            ->where('item_type', 'finished_good')
            ->count());

        $this->assertSame(1, DB::table('products')
            ->where('tenant_id', $tenantB->id)
            ->where('product_mode', 'default')
            ->where('item_type', 'finished_good')
            ->count());
    }

    public function test_item_type_migrations_can_rollback_and_migrate_again(): void
    {
        $addItemTypeMigration = require database_path('migrations/2026_03_13_100000_add_item_type_to_products_table.php');
        $checkpointMigration = require database_path('migrations/2026_03_13_100100_create_product_item_type_backfill_checkpoints_table.php');

        $this->assertTrue(DB::getSchemaBuilder()->hasColumn('products', 'item_type'));
        $this->assertTrue(DB::getSchemaBuilder()->hasTable('product_item_type_backfill_checkpoints'));

        $checkpointMigration->down();
        $addItemTypeMigration->down();

        $this->assertFalse(DB::getSchemaBuilder()->hasTable('product_item_type_backfill_checkpoints'));
        $this->assertFalse(DB::getSchemaBuilder()->hasColumn('products', 'item_type'));

        $addItemTypeMigration->up();
        $checkpointMigration->up();

        $this->assertTrue(DB::getSchemaBuilder()->hasColumn('products', 'item_type'));
        $this->assertTrue(DB::getSchemaBuilder()->hasTable('product_item_type_backfill_checkpoints'));
    }
}
