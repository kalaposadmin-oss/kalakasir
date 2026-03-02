<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->date('transaction_date')->nullable()->after('created_at');
            $table->index(['tenant_id', 'transaction_date'], 'sales_tenant_transaction_date_idx');
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->date('transaction_date')->nullable()->after('created_at');
            $table->index(['tenant_id', 'transaction_date'], 'purchases_tenant_transaction_date_idx');
        });

        Schema::table('purchase_returns', function (Blueprint $table) {
            $table->date('transaction_date')->nullable()->after('created_at');
            $table->index(['tenant_id', 'transaction_date'], 'purchase_returns_tenant_transaction_date_idx');
        });

        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->date('transaction_date')->nullable()->after('created_at');
            $table->index(['tenant_id', 'transaction_date'], 'stock_transfers_tenant_transaction_date_idx');
        });

        Schema::table('stock_adjustments', function (Blueprint $table) {
            $table->date('transaction_date')->nullable()->after('created_at');
            $table->index(['tenant_id', 'transaction_date'], 'stock_adjustments_tenant_transaction_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex('sales_tenant_transaction_date_idx');
            $table->dropColumn('transaction_date');
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropIndex('purchases_tenant_transaction_date_idx');
            $table->dropColumn('transaction_date');
        });

        Schema::table('purchase_returns', function (Blueprint $table) {
            $table->dropIndex('purchase_returns_tenant_transaction_date_idx');
            $table->dropColumn('transaction_date');
        });

        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->dropIndex('stock_transfers_tenant_transaction_date_idx');
            $table->dropColumn('transaction_date');
        });

        Schema::table('stock_adjustments', function (Blueprint $table) {
            $table->dropIndex('stock_adjustments_tenant_transaction_date_idx');
            $table->dropColumn('transaction_date');
        });
    }
};
