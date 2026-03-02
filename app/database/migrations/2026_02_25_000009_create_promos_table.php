<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants');
            $table->string('name');
            $table->string('type');
            $table->decimal('value', 15, 2)->default(0);
            $table->decimal('min_purchase', 15, 2)->nullable();
            $table->string('category')->nullable();
            $table->string('code')->nullable();
            $table->string('status')->default('active');
            $table->date('ends_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promos');
    }
};
