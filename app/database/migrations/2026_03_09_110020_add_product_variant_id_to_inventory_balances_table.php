<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_balances', function (Blueprint $table) {
            $table->foreignUuid('product_variant_id')->nullable()->constrained('product_variants')->restrictOnDelete();
            $table->index(['tenant_id', 'location_id', 'product_variant_id']);
            $table->unique(['tenant_id', 'location_id', 'product_variant_id']);
        });
    }

    public function down(): void
    {
        Schema::table('inventory_balances', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'location_id', 'product_variant_id']);
            $table->dropIndex(['tenant_id', 'location_id', 'product_variant_id']);
            $table->dropConstrainedForeignId('product_variant_id');
        });
    }
};
