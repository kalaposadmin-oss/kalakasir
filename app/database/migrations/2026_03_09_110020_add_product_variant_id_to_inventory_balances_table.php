<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_balances', function (Blueprint $table) {
            $table->foreignUuid('product_variant_id')
                ->nullable()
                ->constrained('product_variants')
                ->restrictOnDelete();

            $table->index(
                ['tenant_id', 'location_id', 'product_variant_id'],
                'inv_bal_tenant_loc_var_idx'
            );

            $table->unique(
                ['tenant_id', 'location_id', 'product_variant_id'],
                'inv_bal_tenant_loc_var_unq'
            );
        });
    }

    public function down(): void
    {
        Schema::table('inventory_balances', function (Blueprint $table) {
            $table->dropUnique('inv_bal_tenant_loc_var_unq');
            $table->dropIndex('inv_bal_tenant_loc_var_idx');
            $table->dropConstrainedForeignId('product_variant_id');
        });
    }
};