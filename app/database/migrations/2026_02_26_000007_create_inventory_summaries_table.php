<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_summaries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants');
            $table->foreignUuid('location_id')->constrained('locations');
            $table->foreignUuid('product_id')->constrained('products');
            $table->decimal('current_qty', 15, 2)->default(0);
            $table->decimal('current_value', 15, 2)->default(0);
            $table->timestampTz('updated_at')->useCurrent();

            $table->unique(['tenant_id', 'location_id', 'product_id']);
            $table->index(['tenant_id', 'location_id']);
            $table->index(['tenant_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_summaries');
    }
};
