<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants');
            $table->foreignUuid('location_id')->constrained('locations');
            $table->string('reference_no')->nullable();
            $table->string('status')->default('draft');
            $table->text('notes')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->date('transaction_date')->nullable();
            $table->timestampTz('posted_at')->nullable();
            $table->uuid('posted_by')->nullable();

            $table->index(['tenant_id', 'status', 'created_at'], 'production_batches_tenant_status_created_idx');
            $table->index(['tenant_id', 'transaction_date'], 'production_batches_tenant_transaction_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_batches');
    }
};