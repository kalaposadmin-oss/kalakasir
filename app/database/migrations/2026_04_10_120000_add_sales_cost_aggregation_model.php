<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (! Schema::hasColumn('sales', 'cogs_total')) {
                $table->decimal('cogs_total', 18, 2)->default(0)->after('total');
            }
        });

        Schema::create('sale_cost_components', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants');
            $table->uuid('sale_id');
            $table->uuid('sale_item_id');
            $table->string('component_type', 40);
            $table->uuid('product_id');
            $table->uuid('product_variant_id')->nullable();
            $table->decimal('qty', 18, 4);
            $table->decimal('unit_cost', 18, 6);
            $table->decimal('total_cost', 18, 2);
            $table->string('source_reference_type', 60);
            $table->uuid('source_reference_id');
            $table->uuid('source_reference_item_id')->nullable();
            $table->string('source', 40)->default('checkout');
            $table->timestampTz('created_at')->nullable();
            $table->timestampTz('updated_at')->nullable();

            $table->index(['tenant_id', 'sale_id'], 'idx_sale_cost_components_sale');
            $table->index(['tenant_id', 'sale_item_id'], 'idx_sale_cost_components_sale_item');
            $table->index(['tenant_id', 'sale_id', 'component_type'], 'idx_sale_cost_components_component_type');
            $table->index(['tenant_id', 'source_reference_type', 'source_reference_id'], 'idx_sale_cost_components_source');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_cost_components');

        Schema::table('sales', function (Blueprint $table) {
            if (Schema::hasColumn('sales', 'cogs_total')) {
                $table->dropColumn('cogs_total');
            }
        });
    }
};