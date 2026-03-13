<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'auto_consume_on_sell_enabled')) {
                $table->boolean('auto_consume_on_sell_enabled')->default(false);
            }

            if (! Schema::hasColumn('tenants', 'allow_negative_raw_material_stock')) {
                $table->boolean('allow_negative_raw_material_stock')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'allow_negative_raw_material_stock')) {
                $table->dropColumn('allow_negative_raw_material_stock');
            }

            if (Schema::hasColumn('tenants', 'auto_consume_on_sell_enabled')) {
                $table->dropColumn('auto_consume_on_sell_enabled');
            }
        });
    }
};
