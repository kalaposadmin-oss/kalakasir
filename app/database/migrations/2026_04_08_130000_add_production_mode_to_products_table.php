<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('production_mode', 32)->default('none')->after('product_mode');
            $table->index(['tenant_id', 'production_mode'], 'products_tenant_production_mode_idx');
        });

        DB::table('products')
            ->where('product_mode', 'bom')
            ->update(['production_mode' => 'auto_consume_on_sell']);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_tenant_production_mode_idx');
            $table->dropColumn('production_mode');
        });
    }
};