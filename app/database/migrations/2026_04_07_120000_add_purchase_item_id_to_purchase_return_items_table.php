<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_return_items', function (Blueprint $table) {
            $table->foreignUuid('purchase_item_id')
                ->nullable()
                ->after('purchase_return_id')
                ->constrained('purchase_items')
                ->restrictOnDelete();
            $table->index(['tenant_id', 'purchase_item_id'], 'purchase_return_items_tenant_purchase_item_idx');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_return_items', function (Blueprint $table) {
            $table->dropIndex('purchase_return_items_tenant_purchase_item_idx');
            $table->dropConstrainedForeignId('purchase_item_id');
        });
    }
};
