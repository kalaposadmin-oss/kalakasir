<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('inventory_balances', function (Blueprint $table) {
            $table->foreignUuid('tenant_id')->constrained('tenants');
            $table->foreignUuid('location_id')->constrained('locations');
            $table->uuid('product_id')->index();
            $table->decimal('qty_on_hand', 15, 2)->default(0);

            $table->unique(['tenant_id', 'location_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_balances');
    }
};
