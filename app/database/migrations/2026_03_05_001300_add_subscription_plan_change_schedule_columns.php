<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('subscriptions')) {
            Schema::table('subscriptions', function (Blueprint $table): void {
                if (! Schema::hasColumn('subscriptions', 'pending_plan')) {
                    $table->string('pending_plan', 1)->nullable()->after('plan');
                }
                if (! Schema::hasColumn('subscriptions', 'pending_change_mode')) {
                    $table->string('pending_change_mode', 20)->nullable()->after('pending_plan');
                }
                if (! Schema::hasColumn('subscriptions', 'pending_change_effective_at')) {
                    $table->timestampTz('pending_change_effective_at')->nullable()->after('pending_change_mode');
                }
                if (! Schema::hasColumn('subscriptions', 'pending_change_reason')) {
                    $table->string('pending_change_reason', 500)->nullable()->after('pending_change_effective_at');
                }
                if (! Schema::hasColumn('subscriptions', 'pending_change_set_by')) {
                    $table->uuid('pending_change_set_by')->nullable()->after('pending_change_reason');
                }
            });
        }

        if (Schema::hasTable('subscription_audit_logs')) {
            Schema::table('subscription_audit_logs', function (Blueprint $table): void {
                if (! Schema::hasColumn('subscription_audit_logs', 'change_mode')) {
                    $table->string('change_mode', 20)->nullable()->after('source');
                }
                if (! Schema::hasColumn('subscription_audit_logs', 'effective_at')) {
                    $table->timestamp('effective_at')->nullable()->after('change_mode');
                }
                if (! Schema::hasColumn('subscription_audit_logs', 'proration_amount')) {
                    $table->decimal('proration_amount', 14, 2)->nullable()->after('effective_at');
                }
                $table->index(['tenant_id', 'change_mode', 'created_at'], 'subscription_audit_logs_mode_created_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('subscriptions')) {
            Schema::table('subscriptions', function (Blueprint $table): void {
                foreach ([
                    'pending_plan',
                    'pending_change_mode',
                    'pending_change_effective_at',
                    'pending_change_reason',
                    'pending_change_set_by',
                ] as $column) {
                    if (Schema::hasColumn('subscriptions', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
