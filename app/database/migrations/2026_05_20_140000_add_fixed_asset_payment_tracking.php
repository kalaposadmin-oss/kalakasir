<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fixed_assets', function (Blueprint $table): void {
            $table->decimal('liability_paid_amount', 15, 2)->default(0)->after('purchase_cash_transaction_id');
            $table->string('liability_status', 30)->default('none')->after('liability_paid_amount');

            $table->index(['tenant_id', 'liability_status'], 'fixed_assets_tenant_liability_status_idx');
        });

        Schema::create('fixed_asset_payment_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('fixed_asset_id')->constrained('fixed_assets')->cascadeOnDelete();
            $table->date('payment_date');
            $table->foreignUuid('payment_account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->foreignUuid('journal_entry_id')->constrained('journal_entries')->restrictOnDelete();
            $table->foreignUuid('cash_transaction_id')->constrained('cash_transactions')->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->text('notes')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['tenant_id', 'fixed_asset_id', 'payment_date'], 'asset_payments_tenant_asset_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_asset_payment_logs');

        Schema::table('fixed_assets', function (Blueprint $table): void {
            $table->dropIndex('fixed_assets_tenant_liability_status_idx');
            $table->dropColumn(['liability_paid_amount', 'liability_status']);
        });
    }
};
