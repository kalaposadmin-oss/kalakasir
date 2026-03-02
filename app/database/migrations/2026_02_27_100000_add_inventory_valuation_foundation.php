<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('inventory_valuation_method', 20)->default('average')->after('timezone');
        });

        Schema::table('stock_ledger', function (Blueprint $table) {
            $table->uuid('reference_item_id')->nullable()->after('reference_id');
            $table->index(['tenant_id', 'reference_type', 'reference_id'], 'idx_stock_ledger_reference');
            $table->index(['tenant_id', 'reference_type', 'reference_id', 'reference_item_id'], 'idx_stock_ledger_reference_item');
        });

        Schema::table('inventory_layers', function (Blueprint $table) {
            $table->foreignUuid('location_id')->nullable()->after('product_id')->constrained('locations');
            $table->decimal('qty_original', 15, 2)->default(0)->after('qty_remaining');
            $table->string('source_type', 40)->nullable()->after('cost_per_unit');
            $table->uuid('source_id')->nullable()->after('source_type');
            $table->timestampTz('received_at')->nullable()->after('source_id');
            $table->timestampTz('created_at')->useCurrent()->after('received_at');

            $table->index(['tenant_id', 'product_id', 'location_id', 'received_at'], 'idx_inventory_layers_fifo');
            $table->index(['tenant_id', 'source_type', 'source_id'], 'idx_inventory_layers_source');
        });

        Schema::create('inventory_issue_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants');
            $table->foreignUuid('product_id')->constrained('products');
            $table->foreignUuid('location_id')->constrained('locations');
            $table->foreignUuid('inventory_layer_id')->nullable()->constrained('inventory_layers');
            $table->string('reference_type', 40);
            $table->uuid('reference_id');
            $table->uuid('reference_item_id')->nullable();
            $table->decimal('qty', 15, 2);
            $table->decimal('unit_cost', 15, 2);
            $table->decimal('total_cost', 15, 2);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('reversed_at')->nullable();

            $table->index(['tenant_id', 'reference_type', 'reference_id'], 'idx_issue_alloc_reference');
            $table->index(['tenant_id', 'product_id', 'location_id'], 'idx_issue_alloc_product_loc');
        });

        Schema::create('inventory_cost_states', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants');
            $table->foreignUuid('product_id')->constrained('products');
            $table->foreignUuid('location_id')->constrained('locations');
            $table->decimal('qty_on_hand', 15, 2)->default(0);
            $table->decimal('average_cost', 15, 2)->default(0);
            $table->decimal('last_cost', 15, 2)->default(0);
            $table->timestampTz('updated_at')->useCurrent();

            $table->unique(['tenant_id', 'product_id', 'location_id'], 'uq_inventory_cost_state');
        });

        Schema::table('stock_transfer_items', function (Blueprint $table) {
            $table->decimal('unit_cost_snapshot', 15, 2)->default(0)->after('qty');
        });

        DB::table('inventory_layers')
            ->whereNull('received_at')
            ->update([
                'qty_original' => DB::raw('qty_remaining'),
                'received_at' => DB::raw('CURRENT_TIMESTAMP'),
                'created_at' => DB::raw('CURRENT_TIMESTAMP'),
            ]);
    }

    public function down(): void
    {
        Schema::table('stock_transfer_items', function (Blueprint $table) {
            $table->dropColumn('unit_cost_snapshot');
        });

        Schema::dropIfExists('inventory_cost_states');
        Schema::dropIfExists('inventory_issue_allocations');

        Schema::table('inventory_layers', function (Blueprint $table) {
            $table->dropIndex('idx_inventory_layers_fifo');
            $table->dropIndex('idx_inventory_layers_source');
            $table->dropConstrainedForeignId('location_id');
            $table->dropColumn(['qty_original', 'source_type', 'source_id', 'received_at', 'created_at']);
        });

        Schema::table('stock_ledger', function (Blueprint $table) {
            $table->dropIndex('idx_stock_ledger_reference');
            $table->dropIndex('idx_stock_ledger_reference_item');
            $table->dropColumn('reference_item_id');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('inventory_valuation_method');
        });
    }
};
