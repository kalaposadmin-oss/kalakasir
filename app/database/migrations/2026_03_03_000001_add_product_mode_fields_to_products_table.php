<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('product_mode', 20)->default('default')->after('track_stock');
            $table->json('variants')->nullable()->after('product_mode');
            $table->json('bom_items')->nullable()->after('variants');
            $table->json('bundle_items')->nullable()->after('bom_items');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('bundle_items');
            $table->dropColumn('bom_items');
            $table->dropColumn('variants');
            $table->dropColumn('product_mode');
        });
    }
};
