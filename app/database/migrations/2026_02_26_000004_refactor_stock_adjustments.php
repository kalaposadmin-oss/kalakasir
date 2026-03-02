<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_adjustments', function (Blueprint $table) {
            $table->string('reference_no')->nullable()->after('location_id');
            $table->string('reason_code')->nullable()->after('reference_no');
            $table->string('status')->default('draft')->after('reason_code');
            $table->text('notes')->nullable()->after('status');
            $table->timestampTz('approved_at')->nullable()->after('notes');
            $table->uuid('approved_by')->nullable()->after('approved_at');
            $table->timestampTz('posted_at')->nullable()->after('approved_by');
            $table->uuid('cancelled_by')->nullable()->after('posted_at');
            $table->timestampTz('cancelled_at')->nullable()->after('cancelled_by');
        });

        Schema::create('stock_adjustment_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants');
            $table->foreignUuid('stock_adjustment_id')->constrained('stock_adjustments');
            $table->foreignUuid('product_id')->constrained('products');
            $table->decimal('system_qty', 15, 2);
            $table->decimal('physical_qty', 15, 2);
            $table->decimal('difference_qty', 15, 2);
            $table->decimal('unit_cost_snapshot', 15, 2)->default(0);

            $table->index(['tenant_id', 'product_id']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants');
            $table->uuid('actor_id')->nullable();
            $table->string('event_type');
            $table->string('reference_type');
            $table->uuid('reference_id');
            $table->json('payload_json');
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['tenant_id', 'reference_type', 'reference_id']);
        });

        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE stock_adjustments MODIFY product_id CHAR(36) NULL');
            DB::statement('ALTER TABLE stock_adjustments MODIFY type VARCHAR(255) NULL');
            DB::statement('ALTER TABLE stock_adjustments MODIFY qty DECIMAL(15,2) NULL');
            DB::statement('ALTER TABLE stock_adjustments MODIFY reason VARCHAR(255) NULL');
            DB::statement('ALTER TABLE stock_adjustments MODIFY balance_before DECIMAL(15,2) NULL');
            DB::statement('ALTER TABLE stock_adjustments MODIFY balance_after DECIMAL(15,2) NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE stock_adjustments ALTER COLUMN product_id DROP NOT NULL');
            DB::statement('ALTER TABLE stock_adjustments ALTER COLUMN type DROP NOT NULL');
            DB::statement('ALTER TABLE stock_adjustments ALTER COLUMN qty DROP NOT NULL');
            DB::statement('ALTER TABLE stock_adjustments ALTER COLUMN reason DROP NOT NULL');
            DB::statement('ALTER TABLE stock_adjustments ALTER COLUMN balance_before DROP NOT NULL');
            DB::statement('ALTER TABLE stock_adjustments ALTER COLUMN balance_after DROP NOT NULL');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('stock_adjustment_items');

        Schema::table('stock_adjustments', function (Blueprint $table) {
            $table->dropColumn([
                'reference_no',
                'reason_code',
                'status',
                'notes',
                'approved_at',
                'approved_by',
                'posted_at',
                'cancelled_by',
                'cancelled_at',
            ]);
        });
    }
};
