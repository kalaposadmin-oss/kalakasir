<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fixed_assets', function (Blueprint $table): void {
            $table->string('payment_method', 30)->default('unposted')->after('supplier_id');
            $table->uuid('payment_account_id')->nullable()->after('payment_method');
            $table->uuid('fixed_asset_account_id')->nullable()->after('payment_account_id');
            $table->uuid('accumulated_depreciation_account_id')->nullable()->after('fixed_asset_account_id');
            $table->uuid('depreciation_expense_account_id')->nullable()->after('accumulated_depreciation_account_id');
            $table->uuid('purchase_journal_entry_id')->nullable()->after('depreciation_expense_account_id');
            $table->uuid('purchase_cash_transaction_id')->nullable()->after('purchase_journal_entry_id');

            $table->index(['tenant_id', 'payment_method'], 'fixed_assets_tenant_payment_method_idx');
            $table->index(['tenant_id', 'purchase_journal_entry_id'], 'fixed_assets_tenant_purchase_journal_idx');
        });

        Schema::table('fixed_asset_depreciation_entries', function (Blueprint $table): void {
            $table->uuid('journal_entry_id')->nullable()->after('book_value');
            $table->index(['tenant_id', 'journal_entry_id'], 'asset_depreciation_tenant_journal_idx');
        });
    }

    public function down(): void
    {
        Schema::table('fixed_asset_depreciation_entries', function (Blueprint $table): void {
            $table->dropIndex('asset_depreciation_tenant_journal_idx');
            $table->dropColumn('journal_entry_id');
        });

        Schema::table('fixed_assets', function (Blueprint $table): void {
            $table->dropIndex('fixed_assets_tenant_payment_method_idx');
            $table->dropIndex('fixed_assets_tenant_purchase_journal_idx');
            $table->dropColumn([
                'payment_method',
                'payment_account_id',
                'fixed_asset_account_id',
                'accumulated_depreciation_account_id',
                'depreciation_expense_account_id',
                'purchase_journal_entry_id',
                'purchase_cash_transaction_id',
            ]);
        });
    }
};
