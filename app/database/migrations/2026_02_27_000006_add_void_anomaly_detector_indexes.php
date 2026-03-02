<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->index(['tenant_id', 'status', 'voided_at'], 'sales_tenant_status_voided_at_idx');
            $table->index(['tenant_id', 'status', 'voided_by', 'voided_at'], 'sales_tenant_status_voided_by_voided_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex('sales_tenant_status_voided_at_idx');
            $table->dropIndex('sales_tenant_status_voided_by_voided_at_idx');
        });
    }
};
