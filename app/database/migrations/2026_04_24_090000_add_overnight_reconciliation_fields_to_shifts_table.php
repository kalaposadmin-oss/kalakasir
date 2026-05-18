<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->boolean('closed_by_system')->default(false)->after('status');
            $table->boolean('requires_reconciliation')->default(false)->after('closed_by_system');
            $table->timestampTz('reconciliation_completed_at')->nullable()->after('requires_reconciliation');
            $table->uuid('reconciliation_completed_by')->nullable()->after('reconciliation_completed_at');

            $table->index(['tenant_id', 'requires_reconciliation'], 'shifts_tenant_reconcile_idx');
        });
    }

    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->dropIndex('shifts_tenant_reconcile_idx');
            $table->dropColumn([
                'closed_by_system',
                'requires_reconciliation',
                'reconciliation_completed_at',
                'reconciliation_completed_by',
            ]);
        });
    }
};
