<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('asset_code', 80);
            $table->string('name', 150);
            $table->string('asset_category', 80)->default('equipment');
            $table->foreignUuid('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignUuid('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('serial_number', 120)->nullable();
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_price', 15, 2)->default(0);
            $table->decimal('residual_value', 15, 2)->default(0);
            $table->unsignedInteger('useful_life_months')->default(0);
            $table->string('depreciation_method', 40)->default('straight_line');
            $table->decimal('accumulated_depreciation', 15, 2)->default(0);
            $table->decimal('book_value', 15, 2)->default(0);
            $table->string('condition', 40)->default('good');
            $table->string('status', 40)->default('active');
            $table->date('warranty_until')->nullable();
            $table->date('last_maintenance_at')->nullable();
            $table->date('next_maintenance_at')->nullable();
            $table->text('notes')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['tenant_id', 'asset_code']);
            $table->index(['tenant_id', 'asset_category', 'status'], 'fixed_assets_tenant_category_status_idx');
            $table->index(['tenant_id', 'location_id'], 'fixed_assets_tenant_location_idx');
        });

        Schema::create('fixed_asset_maintenance_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('fixed_asset_id')->constrained('fixed_assets')->cascadeOnDelete();
            $table->date('maintenance_date');
            $table->string('maintenance_type', 60)->default('routine');
            $table->string('vendor_name', 150)->nullable();
            $table->decimal('cost', 15, 2)->default(0);
            $table->string('condition_before', 40)->nullable();
            $table->string('condition_after', 40)->nullable();
            $table->date('next_maintenance_at')->nullable();
            $table->text('notes')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'fixed_asset_id', 'maintenance_date'], 'asset_maintenance_asset_date_idx');
        });

        Schema::create('fixed_asset_depreciation_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('fixed_asset_id')->constrained('fixed_assets')->cascadeOnDelete();
            $table->date('period_date');
            $table->decimal('depreciation_amount', 15, 2);
            $table->decimal('accumulated_depreciation', 15, 2);
            $table->decimal('book_value', 15, 2);
            $table->uuid('posted_by')->nullable();
            $table->timestampTz('posted_at')->nullable();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'fixed_asset_id', 'period_date'], 'asset_depreciation_unique_period');
        });

        Schema::create('fixed_asset_audits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('fixed_asset_id')->constrained('fixed_assets')->cascadeOnDelete();
            $table->date('audit_date');
            $table->foreignUuid('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('physical_status', 40)->default('found');
            $table->string('condition', 40)->nullable();
            $table->text('notes')->nullable();
            $table->uuid('audited_by')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'audit_date'], 'asset_audits_tenant_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_asset_audits');
        Schema::dropIfExists('fixed_asset_depreciation_entries');
        Schema::dropIfExists('fixed_asset_maintenance_logs');
        Schema::dropIfExists('fixed_assets');
    }
};
