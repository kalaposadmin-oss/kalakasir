<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_layers', function (Blueprint $table) {
            $table->uuid('source_item_id')->nullable()->after('source_id');
            $table->index(
                ['tenant_id', 'source_type', 'source_id', 'source_item_id'],
                'idx_inventory_layers_source_item'
            );
        });
    }

    public function down(): void
    {
        Schema::table('inventory_layers', function (Blueprint $table) {
            $table->dropIndex('idx_inventory_layers_source_item');
            $table->dropColumn('source_item_id');
        });
    }
};
