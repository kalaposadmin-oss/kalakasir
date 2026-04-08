<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refund_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignUuid('refund_id')->constrained('refunds')->restrictOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('qty', 15, 2);
            $table->decimal('amount', 15, 2)->default(0);

            $table->index(['refund_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_items');
    }
};
