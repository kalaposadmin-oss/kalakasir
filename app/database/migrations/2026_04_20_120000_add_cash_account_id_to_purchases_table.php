<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->uuid('cash_account_id')->nullable()->after('payment_method');
            $table->index(['tenant_id', 'cash_account_id'], 'purchases_tenant_cash_account_idx');
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropIndex('purchases_tenant_cash_account_idx');
            $table->dropColumn('cash_account_id');
        });
    }
};