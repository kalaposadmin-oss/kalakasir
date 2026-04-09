<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('products', 'item_type')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->string('item_type', 20)->nullable()->after('product_mode');
            $table->index(['tenant_id', 'item_type'], 'products_tenant_item_type_idx');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_tenant_item_type_idx');
            $table->dropColumn('item_type');
        });
    }
};
