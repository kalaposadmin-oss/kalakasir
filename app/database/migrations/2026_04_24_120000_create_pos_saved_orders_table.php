<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_saved_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants');
            $table->foreignUuid('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->uuid('shift_id')->nullable();
            $table->foreignUuid('register_id')->nullable()->constrained('registers')->nullOnDelete();
            $table->foreignUuid('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('holder_name', 120);
            $table->text('note')->nullable();
            $table->json('payload');
            $table->decimal('total', 15, 2)->default(0);
            $table->unsignedInteger('item_count')->default(0);
            $table->uuid('created_by')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['tenant_id', 'created_at'], 'pos_saved_orders_tenant_created_idx');
            $table->index(['tenant_id', 'holder_name'], 'pos_saved_orders_tenant_holder_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_saved_orders');
    }
};
