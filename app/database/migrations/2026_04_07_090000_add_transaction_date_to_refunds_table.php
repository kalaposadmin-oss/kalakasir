<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->date('transaction_date')->nullable()->after('reason');
            $table->index(['tenant_id', 'transaction_date'], 'idx_refunds_tenant_transaction_date');
        });

        DB::table('refunds')
            ->whereNull('transaction_date')
            ->update([
                'transaction_date' => DB::raw('DATE(created_at)'),
            ]);
    }

    public function down(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->dropIndex('idx_refunds_tenant_transaction_date');
            $table->dropColumn('transaction_date');
        });
    }
};
