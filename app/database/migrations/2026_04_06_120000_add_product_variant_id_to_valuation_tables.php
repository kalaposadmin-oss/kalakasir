<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const NULL_VARIANT_SENTINEL = '00000000-0000-0000-0000-000000000000';

    public function up(): void
    {
        Schema::table('inventory_layers', function (Blueprint $table) {
            $table->foreignUuid('product_variant_id')
                ->nullable()
                ->after('product_id')
                ->constrained('product_variants')
                ->restrictOnDelete();

            $table->dropIndex('idx_inventory_layers_fifo');
            $table->index(
                ['tenant_id', 'product_id', 'product_variant_id', 'location_id', 'received_at'],
                'idx_inventory_layers_fifo_variant'
            );
        });

        Schema::table('inventory_issue_allocations', function (Blueprint $table) {
            $table->foreignUuid('product_variant_id')
                ->nullable()
                ->after('product_id')
                ->constrained('product_variants')
                ->restrictOnDelete();

            $table->dropIndex('idx_issue_alloc_product_loc');
            $table->index(
                ['tenant_id', 'product_id', 'product_variant_id', 'location_id'],
                'idx_issue_alloc_product_variant_loc'
            );
        });

        Schema::table('inventory_cost_states', function (Blueprint $table) {
            $table->foreignUuid('product_variant_id')
                ->nullable()
                ->after('product_id')
                ->constrained('product_variants')
                ->restrictOnDelete();

            $table->dropUnique('uq_inventory_cost_state');
            $table->index(
                ['tenant_id', 'product_id', 'product_variant_id', 'location_id'],
                'idx_inventory_cost_state_variant_bucket'
            );
        });

        $this->createVariantBucketUniqueIndex();
    }

    public function down(): void
    {
        $this->dropVariantBucketUniqueIndex();

        Schema::table('inventory_cost_states', function (Blueprint $table) {
            $table->dropIndex('idx_inventory_cost_state_variant_bucket');
            $table->dropConstrainedForeignId('product_variant_id');
            $table->unique(['tenant_id', 'product_id', 'location_id'], 'uq_inventory_cost_state');
        });

        Schema::table('inventory_issue_allocations', function (Blueprint $table) {
            $table->dropIndex('idx_issue_alloc_product_variant_loc');
            $table->dropConstrainedForeignId('product_variant_id');
            $table->index(['tenant_id', 'product_id', 'location_id'], 'idx_issue_alloc_product_loc');
        });

        Schema::table('inventory_layers', function (Blueprint $table) {
            $table->dropIndex('idx_inventory_layers_fifo_variant');
            $table->dropConstrainedForeignId('product_variant_id');
            $table->index(['tenant_id', 'product_id', 'location_id', 'received_at'], 'idx_inventory_layers_fifo');
        });
    }

    private function createVariantBucketUniqueIndex(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement(sprintf(
                "CREATE UNIQUE INDEX uq_inventory_cost_state_variant ON inventory_cost_states (tenant_id, product_id, (coalesce(product_variant_id, '%s')), location_id)",
                self::NULL_VARIANT_SENTINEL
            ));

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement(sprintf(
                "CREATE UNIQUE INDEX uq_inventory_cost_state_variant ON inventory_cost_states (tenant_id, product_id, (coalesce(product_variant_id, '%s'::uuid)), location_id)",
                self::NULL_VARIANT_SENTINEL
            ));

            return;
        }

        DB::statement(sprintf(
            "CREATE UNIQUE INDEX uq_inventory_cost_state_variant ON inventory_cost_states (tenant_id, product_id, coalesce(product_variant_id, '%s'), location_id)",
            self::NULL_VARIANT_SENTINEL
        ));
    }

    private function dropVariantBucketUniqueIndex(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('DROP INDEX uq_inventory_cost_state_variant ON inventory_cost_states');

            return;
        }

        DB::statement('DROP INDEX uq_inventory_cost_state_variant');
    }
};