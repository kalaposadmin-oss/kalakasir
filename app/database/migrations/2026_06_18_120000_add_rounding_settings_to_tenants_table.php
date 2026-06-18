<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'rounding_mode')) {
                $table->string('rounding_mode', 20)->default('none')->after('allow_negative_raw_material_stock');
            }

            if (! Schema::hasColumn('tenants', 'rounding_increment')) {
                $table->unsignedInteger('rounding_increment')->default(0)->after('rounding_mode');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'rounding_increment')) {
                $table->dropColumn('rounding_increment');
            }

            if (Schema::hasColumn('tenants', 'rounding_mode')) {
                $table->dropColumn('rounding_mode');
            }
        });
    }
};
