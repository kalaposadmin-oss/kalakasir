<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_settlements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->date('sales_date');
            $table->date('settlement_date');
            $table->string('payment_method', 40)->default('qris');
            $table->foreignUuid('settlement_account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->foreignUuid('bank_account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->foreignUuid('fee_account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->foreignUuid('journal_entry_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
            $table->decimal('expected_amount', 15, 2);
            $table->decimal('received_amount', 15, 2);
            $table->decimal('admin_fee_amount', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['tenant_id', 'sales_date', 'payment_method'], 'payment_settlements_unique_day_method');
            $table->index(['tenant_id', 'settlement_date'], 'payment_settlements_tenant_settlement_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_settlements');
    }
};
