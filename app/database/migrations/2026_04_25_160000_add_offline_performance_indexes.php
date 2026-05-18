<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sales')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->index(['tenant_id', 'status', 'transaction_date'], 'sales_tenant_status_tx_date_idx');
                $table->index(['tenant_id', 'status', 'branch_id', 'transaction_date'], 'sales_tenant_status_branch_tx_idx');
            });
        }

        if (Schema::hasTable('sale_items')) {
            Schema::table('sale_items', function (Blueprint $table) {
                $table->index(['tenant_id', 'sale_id', 'product_id'], 'sale_items_tenant_sale_product_idx');
            });
        }

        if (Schema::hasTable('refunds')) {
            Schema::table('refunds', function (Blueprint $table) {
                $table->index(['tenant_id', 'status', 'transaction_date'], 'refunds_tenant_status_tx_date_idx');
                $table->index(['tenant_id', 'status', 'created_at'], 'refunds_tenant_status_created_idx');
            });
        }

        if (Schema::hasTable('refund_items')) {
            Schema::table('refund_items', function (Blueprint $table) {
                $table->index(['tenant_id', 'refund_id', 'product_id'], 'refund_items_tenant_refund_product_idx');
            });
        }

        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                $table->index(['tenant_id', 'is_active', 'name'], 'products_tenant_active_name_idx');
                $table->index(['tenant_id', 'is_active', 'category_id'], 'products_tenant_active_category_idx');
                $table->index(['tenant_id', 'track_stock', 'min_stock'], 'products_tenant_track_min_stock_idx');
            });
        }

        if (Schema::hasTable('purchases')) {
            Schema::table('purchases', function (Blueprint $table) {
                $table->index(['tenant_id', 'created_at'], 'purchases_tenant_created_idx');
            });
        }

        if (Schema::hasTable('purchase_items')) {
            Schema::table('purchase_items', function (Blueprint $table) {
                $table->index(['tenant_id', 'product_id', 'purchase_id'], 'purchase_items_tenant_product_purchase_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('purchase_items')) {
            Schema::table('purchase_items', function (Blueprint $table) {
                $table->dropIndex('purchase_items_tenant_product_purchase_idx');
            });
        }

        if (Schema::hasTable('purchases')) {
            Schema::table('purchases', function (Blueprint $table) {
                $table->dropIndex('purchases_tenant_created_idx');
            });
        }

        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropIndex('products_tenant_active_name_idx');
                $table->dropIndex('products_tenant_active_category_idx');
                $table->dropIndex('products_tenant_track_min_stock_idx');
            });
        }

        if (Schema::hasTable('refund_items')) {
            Schema::table('refund_items', function (Blueprint $table) {
                $table->dropIndex('refund_items_tenant_refund_product_idx');
            });
        }

        if (Schema::hasTable('refunds')) {
            Schema::table('refunds', function (Blueprint $table) {
                $table->dropIndex('refunds_tenant_status_tx_date_idx');
                $table->dropIndex('refunds_tenant_status_created_idx');
            });
        }

        if (Schema::hasTable('sale_items')) {
            Schema::table('sale_items', function (Blueprint $table) {
                $table->dropIndex('sale_items_tenant_sale_product_idx');
            });
        }

        if (Schema::hasTable('sales')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->dropIndex('sales_tenant_status_tx_date_idx');
                $table->dropIndex('sales_tenant_status_branch_tx_idx');
            });
        }
    }
};
