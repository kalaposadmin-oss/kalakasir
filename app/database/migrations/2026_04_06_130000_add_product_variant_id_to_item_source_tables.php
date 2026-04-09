<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_transfer_items', function (Blueprint $table) {
            $table->foreignUuid('product_variant_id')
                ->nullable()
                ->after('product_id')
                ->constrained('product_variants')
                ->restrictOnDelete();
            $table->index(['tenant_id', 'product_id', 'product_variant_id'], 'stock_transfer_items_tenant_product_variant_idx');
        });

        Schema::table('stock_adjustment_items', function (Blueprint $table) {
            $table->foreignUuid('product_variant_id')
                ->nullable()
                ->after('product_id')
                ->constrained('product_variants')
                ->restrictOnDelete();
            $table->index(['tenant_id', 'product_id', 'product_variant_id'], 'stock_adjustment_items_tenant_product_variant_idx');
        });

        Schema::table('production_batch_items', function (Blueprint $table) {
            $table->foreignUuid('product_variant_id')
                ->nullable()
                ->after('product_id')
                ->constrained('product_variants')
                ->restrictOnDelete();
            $table->index(['tenant_id', 'product_id', 'product_variant_id'], 'production_batch_items_tenant_product_variant_idx');
        });

        Schema::table('purchase_return_items', function (Blueprint $table) {
            $table->foreignUuid('product_variant_id')
                ->nullable()
                ->after('product_id')
                ->constrained('product_variants')
                ->restrictOnDelete();
            $table->index(['tenant_id', 'product_id', 'product_variant_id'], 'purchase_return_items_tenant_product_variant_idx');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_return_items', function (Blueprint $table) {
            $table->dropIndex('purchase_return_items_tenant_product_variant_idx');
            $table->dropConstrainedForeignId('product_variant_id');
        });

        Schema::table('production_batch_items', function (Blueprint $table) {
            $table->dropIndex('production_batch_items_tenant_product_variant_idx');
            $table->dropConstrainedForeignId('product_variant_id');
        });

        Schema::table('stock_adjustment_items', function (Blueprint $table) {
            $table->dropIndex('stock_adjustment_items_tenant_product_variant_idx');
            $table->dropConstrainedForeignId('product_variant_id');
        });

        Schema::table('stock_transfer_items', function (Blueprint $table) {
            $table->dropIndex('stock_transfer_items_tenant_product_variant_idx');
            $table->dropConstrainedForeignId('product_variant_id');
        });
    }
};