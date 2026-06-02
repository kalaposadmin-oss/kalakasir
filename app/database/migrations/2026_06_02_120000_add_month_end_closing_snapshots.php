<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lock_periods', function (Blueprint $table): void {
            if (! Schema::hasColumn('lock_periods', 'name')) {
                $table->string('name')->nullable()->after('tenant_id');
            }
            if (! Schema::hasColumn('lock_periods', 'closed_by')) {
                $table->uuid('closed_by')->nullable()->after('reason');
            }
            if (! Schema::hasColumn('lock_periods', 'closed_at')) {
                $table->timestampTz('closed_at')->nullable()->after('closed_by');
            }
            if (! Schema::hasColumn('lock_periods', 'reopened_by')) {
                $table->uuid('reopened_by')->nullable()->after('closed_at');
            }
            if (! Schema::hasColumn('lock_periods', 'reopened_at')) {
                $table->timestampTz('reopened_at')->nullable()->after('reopened_by');
            }
            if (! Schema::hasColumn('lock_periods', 'close_summary_json')) {
                $table->json('close_summary_json')->nullable()->after('reopened_at');
            }
        });

        if (! Schema::hasTable('period_closing_balances')) {
            Schema::create('period_closing_balances', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
                $table->foreignUuid('lock_period_id')->constrained('lock_periods')->cascadeOnDelete();
                $table->string('balance_type', 40);
                $table->string('reference_type', 40)->nullable();
                $table->uuid('reference_id')->nullable();
                $table->string('label')->nullable();
                $table->decimal('quantity', 15, 4)->nullable();
                $table->decimal('amount', 15, 2)->default(0);
                $table->json('payload_json')->nullable();
                $table->timestampTz('created_at')->useCurrent();

                $table->index(['tenant_id', 'lock_period_id'], 'period_close_balances_tenant_period_idx');
                $table->index(['tenant_id', 'balance_type'], 'period_close_balances_tenant_type_idx');
                $table->index(['tenant_id', 'reference_type', 'reference_id'], 'period_close_balances_reference_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('period_closing_balances');

        Schema::table('lock_periods', function (Blueprint $table): void {
            $columns = [
                'name',
                'closed_by',
                'closed_at',
                'reopened_by',
                'reopened_at',
                'close_summary_json',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('lock_periods', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
