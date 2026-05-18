<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('finance_audit_logs')) {
            return;
        }

        Schema::create('finance_audit_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants');
            $table->date('audit_date');
            $table->string('status', 16);
            $table->decimal('inventory_vs_gl_difference', 15, 2)->default(0);
            $table->decimal('cash_vs_gl_difference', 15, 2)->default(0);
            $table->unsignedInteger('unbalanced_journal_count')->default(0);
            $table->unsignedInteger('orphan_transaction_count')->default(0);
            $table->json('payload_json');
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['tenant_id', 'audit_date'], 'finance_audit_logs_tenant_date_idx');
            $table->index(['tenant_id', 'status', 'audit_date'], 'finance_audit_logs_tenant_status_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_audit_logs');
    }
};
