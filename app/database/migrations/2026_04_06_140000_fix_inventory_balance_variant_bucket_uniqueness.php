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
        Schema::table('inventory_balances', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'location_id', 'product_variant_id']);
            $table->dropUnique(['tenant_id', 'location_id', 'product_id']);
            $table->index(['tenant_id', 'product_id', 'product_variant_id', 'location_id'], 'inv_balances_variant_bucket_idx');
        });

        $this->createVariantBucketUniqueIndex();
    }

    public function down(): void
    {
        $this->dropVariantBucketUniqueIndex();

        Schema::table('inventory_balances', function (Blueprint $table) {
            $table->dropIndex('inv_balances_variant_bucket_idx');
            $table->unique(['tenant_id', 'location_id', 'product_variant_id']);
            $table->unique(['tenant_id', 'location_id', 'product_id']);
        });
    }

    private function createVariantBucketUniqueIndex(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement(sprintf(
                "CREATE UNIQUE INDEX uq_inventory_balances_variant_bucket ON inventory_balances (tenant_id, product_id, (coalesce(product_variant_id, '%s')), location_id)",
                self::NULL_VARIANT_SENTINEL
            ));

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement(sprintf(
                "CREATE UNIQUE INDEX uq_inventory_balances_variant_bucket ON inventory_balances (tenant_id, product_id, (coalesce(product_variant_id, '%s'::uuid)), location_id)",
                self::NULL_VARIANT_SENTINEL
            ));

            return;
        }

        DB::statement(sprintf(
            "CREATE UNIQUE INDEX uq_inventory_balances_variant_bucket ON inventory_balances (tenant_id, product_id, coalesce(product_variant_id, '%s'), location_id)",
            self::NULL_VARIANT_SENTINEL
        ));
    }

    private function dropVariantBucketUniqueIndex(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('DROP INDEX uq_inventory_balances_variant_bucket ON inventory_balances');

            return;
        }

        DB::statement('DROP INDEX uq_inventory_balances_variant_bucket');
    }
};