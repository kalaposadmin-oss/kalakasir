<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->index(['tenant_id', 'status', 'created_at'], 'sales_tenant_status_created_at_idx');
            $table->index(['tenant_id', 'branch_id', 'created_at'], 'sales_tenant_branch_created_at_idx');
        });

        Schema::table('stock_ledger', function (Blueprint $table) {
            $table->index(['tenant_id', 'product_id', 'location_id'], 'stock_ledger_tenant_product_location_idx');
            $table->index(['tenant_id', 'reference_type', 'reference_id'], 'stock_ledger_tenant_reference_idx');
            $table->index(['tenant_id', 'created_at'], 'stock_ledger_tenant_created_at_idx');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index(['tenant_id', 'event_type'], 'audit_logs_tenant_event_idx');
            $table->index(['tenant_id', 'created_at'], 'audit_logs_tenant_created_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex('sales_tenant_status_created_at_idx');
            $table->dropIndex('sales_tenant_branch_created_at_idx');
        });

        Schema::table('stock_ledger', function (Blueprint $table) {
            $table->dropIndex('stock_ledger_tenant_product_location_idx');
            $table->dropIndex('stock_ledger_tenant_reference_idx');
            $table->dropIndex('stock_ledger_tenant_created_at_idx');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('audit_logs_tenant_event_idx');
            $table->dropIndex('audit_logs_tenant_created_at_idx');
        });
    }
};
