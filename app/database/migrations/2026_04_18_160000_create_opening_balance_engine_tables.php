<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opening_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants');
            $table->date('opening_date');
            $table->string('status', 24)->default('draft');
            $table->foreignUuid('equity_account_id')->nullable()->constrained('chart_of_accounts');
            $table->decimal('equity_amount', 15, 2)->nullable();
            $table->timestampTz('posted_at')->nullable();
            $table->uuid('posted_by')->nullable();
            $table->timestampTz('locked_at')->nullable();
            $table->uuid('created_by')->nullable();
            $table->json('meta')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'opening_date']);
        });

        Schema::create('opening_stock_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('opening_session_id')->constrained('opening_sessions')->cascadeOnDelete();
            $table->foreignUuid('tenant_id')->constrained('tenants');
            $table->foreignUuid('location_id')->constrained('locations');
            $table->foreignUuid('product_id')->constrained('products');
            $table->foreignUuid('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->decimal('qty', 15, 4);
            $table->decimal('unit_cost', 15, 2);
            $table->decimal('line_value', 15, 2);
            $table->string('reference_note', 255)->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'opening_session_id']);
            $table->index(['tenant_id', 'location_id', 'product_id']);
            $table->unique(['opening_session_id', 'location_id', 'product_id', 'product_variant_id'], 'opening_stock_lines_unique_bucket');
        });

        Schema::create('opening_fund_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('opening_session_id')->constrained('opening_sessions')->cascadeOnDelete();
            $table->foreignUuid('tenant_id')->constrained('tenants');
            $table->foreignUuid('account_id')->constrained('chart_of_accounts');
            $table->string('source_type', 24);
            $table->decimal('amount', 15, 2);
            $table->string('reference_note', 255)->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'opening_session_id']);
            $table->index(['tenant_id', 'source_type']);
            $table->unique(['opening_session_id', 'account_id', 'source_type'], 'opening_fund_lines_unique_account_source');
        });

        Schema::create('opening_posting_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('opening_session_id')->constrained('opening_sessions')->cascadeOnDelete();
            $table->foreignUuid('tenant_id')->constrained('tenants');
            $table->foreignUuid('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->unsignedInteger('stock_ledger_count')->default(0);
            $table->unsignedInteger('inventory_layer_count')->default(0);
            $table->json('payload')->nullable();
            $table->timestampTz('posted_at')->useCurrent();
            $table->uuid('created_by')->nullable();

            $table->index(['tenant_id', 'posted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opening_posting_logs');
        Schema::dropIfExists('opening_fund_lines');
        Schema::dropIfExists('opening_stock_lines');
        Schema::dropIfExists('opening_sessions');
    }
};
