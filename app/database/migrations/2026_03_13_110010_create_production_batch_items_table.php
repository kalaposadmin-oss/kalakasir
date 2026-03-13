<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_batch_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants');
            $table->foreignUuid('production_batch_id')->constrained('production_batches');
            $table->string('line_type');
            $table->foreignUuid('product_id')->constrained('products');
            $table->decimal('qty', 15, 2);
            $table->decimal('unit_cost_snapshot', 15, 2)->default(0);

            $table->index(['tenant_id', 'production_batch_id', 'line_type'], 'production_batch_items_tenant_batch_type_idx');
            $table->index(['tenant_id', 'product_id'], 'production_batch_items_tenant_product_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_batch_items');
    }
};