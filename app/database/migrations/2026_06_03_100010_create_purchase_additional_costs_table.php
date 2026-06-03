<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_additional_costs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('purchase_id')->constrained('purchases')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('category', 60)->default('packaging');
            $table->decimal('amount', 15, 2);
            $table->timestampTz('posted_at')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'purchase_id']);
            $table->index(['tenant_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_additional_costs');
    }
};
