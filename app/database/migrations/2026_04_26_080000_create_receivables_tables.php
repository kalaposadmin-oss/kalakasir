<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receivables', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants');
            $table->foreignUuid('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignUuid('sale_id')->constrained('sales')->restrictOnDelete();
            $table->decimal('total_amount', 15, 2);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->decimal('remaining_amount', 15, 2)->default(0);
            $table->date('due_date')->nullable();
            $table->string('status', 20)->default('unpaid');
            $table->uuid('created_by')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->nullable();

            $table->unique(['tenant_id', 'sale_id'], 'receivables_tenant_sale_unique');
            $table->index(['tenant_id', 'customer_id'], 'receivables_tenant_customer_idx');
            $table->index(['tenant_id', 'status'], 'receivables_tenant_status_idx');
            $table->index(['tenant_id', 'due_date'], 'receivables_tenant_due_date_idx');
            $table->index(['tenant_id', 'status', 'due_date'], 'receivables_tenant_status_due_idx');
            $table->index(['tenant_id', 'created_at'], 'receivables_tenant_created_idx');
        });

        Schema::create('receivable_payments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants');
            $table->foreignUuid('receivable_id')->constrained('receivables')->cascadeOnDelete();
            $table->foreignUuid('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignUuid('sale_id')->nullable()->constrained('sales')->nullOnDelete();
            $table->uuid('account_id')->nullable();
            $table->foreignUuid('payment_method_id')->nullable()->constrained('payment_methods')->nullOnDelete();
            $table->string('payment_method', 50);
            $table->decimal('amount', 15, 2);
            $table->text('notes')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['tenant_id', 'receivable_id', 'created_at'], 'receivable_payments_tenant_receivable_created_idx');
            $table->index(['tenant_id', 'customer_id', 'created_at'], 'receivable_payments_tenant_customer_created_idx');
            $table->index(['tenant_id', 'sale_id'], 'receivable_payments_tenant_sale_idx');
            $table->index(['tenant_id', 'account_id'], 'receivable_payments_tenant_account_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receivable_payments');
        Schema::dropIfExists('receivables');
    }
};
