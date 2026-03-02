<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('account_mappings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants');
            $table->uuid('sale_account_id')->index();
            $table->uuid('cash_account_id')->index();
            $table->uuid('bank_account_id')->index();
            $table->uuid('inventory_account_id')->index();
            $table->uuid('cogs_account_id')->index();
            $table->uuid('payable_account_id')->index();
            $table->uuid('receivable_account_id')->index();
            $table->uuid('expense_adjustment_account_id')->index();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();

            $table->unique(['tenant_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_mappings');
    }
};
