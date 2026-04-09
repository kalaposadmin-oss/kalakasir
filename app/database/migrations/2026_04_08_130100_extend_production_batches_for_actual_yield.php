<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_batches', function (Blueprint $table) {
            $table->foreignUuid('branch_id')->nullable()->after('tenant_id')->constrained('branches');
            $table->foreignUuid('product_id')->nullable()->after('location_id')->constrained('products');
            $table->uuid('product_variant_id')->nullable()->after('product_id');
            $table->unsignedInteger('recipe_version')->nullable()->after('product_variant_id');
            $table->decimal('expected_output_qty', 15, 4)->default(0)->after('transaction_date');
            $table->decimal('actual_output_qty', 15, 4)->default(0)->after('expected_output_qty');
            $table->decimal('yield_variance_qty', 15, 4)->default(0)->after('actual_output_qty');
            $table->decimal('yield_variance_pct', 9, 4)->default(0)->after('yield_variance_qty');
            $table->text('variance_reason')->nullable()->after('yield_variance_pct');
            $table->string('idempotency_key', 120)->nullable()->after('posted_by');
            $table->timestampTz('updated_at')->nullable()->after('created_at');
            $table->timestampTz('cancelled_at')->nullable()->after('posted_at');
            $table->uuid('cancelled_by')->nullable()->after('cancelled_at');

            $table->index(['tenant_id', 'branch_id', 'transaction_date'], 'production_batches_tenant_branch_date_idx');
            $table->index(['tenant_id', 'status'], 'production_batches_tenant_status_idx_v2');
            $table->unique(['tenant_id', 'idempotency_key'], 'production_batches_tenant_idempotency_unique');
        });
    }

    public function down(): void
    {
        Schema::table('production_batches', function (Blueprint $table) {
            $table->dropUnique('production_batches_tenant_idempotency_unique');
            $table->dropIndex('production_batches_tenant_branch_date_idx');
            $table->dropIndex('production_batches_tenant_status_idx_v2');
            $table->dropConstrainedForeignId('branch_id');
            $table->dropConstrainedForeignId('product_id');
            $table->dropColumn([
                'product_variant_id',
                'recipe_version',
                'expected_output_qty',
                'actual_output_qty',
                'yield_variance_qty',
                'yield_variance_pct',
                'variance_reason',
                'idempotency_key',
                'updated_at',
                'cancelled_at',
                'cancelled_by',
            ]);
        });
    }
};