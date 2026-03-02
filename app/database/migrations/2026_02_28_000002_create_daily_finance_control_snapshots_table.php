<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_finance_control_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants');
            $table->date('snapshot_date');
            $table->decimal('sales_total', 15, 2)->default(0);
            $table->decimal('cogs_total', 15, 2)->default(0);
            $table->decimal('cash_closing_total', 15, 2)->default(0);
            $table->decimal('debt_due_7d', 15, 2)->default(0);
            $table->decimal('cash_difference_total', 15, 2)->default(0);
            $table->unsignedInteger('shift_closed_count')->default(0);
            $table->unsignedInteger('transaction_count')->default(0);
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['tenant_id', 'snapshot_date'], 'finance_snapshots_tenant_date_uq');
            $table->index(['tenant_id', 'snapshot_date'], 'finance_snapshots_tenant_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_finance_control_snapshots');
    }
};
