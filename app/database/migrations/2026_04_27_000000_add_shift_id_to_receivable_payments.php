<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receivable_payments', function (Blueprint $table): void {
            $table->uuid('shift_id')->nullable()->after('sale_id');
            $table->index(['tenant_id', 'shift_id'], 'receivable_payments_tenant_shift_idx');
        });
    }

    public function down(): void
    {
        Schema::table('receivable_payments', function (Blueprint $table): void {
            $table->dropIndex('receivable_payments_tenant_shift_idx');
            $table->dropColumn('shift_id');
        });
    }
};
