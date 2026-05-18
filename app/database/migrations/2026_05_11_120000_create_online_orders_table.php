<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('online_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignUuid('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignUuid('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('order_code', 32);
            $table->string('customer_name', 150);
            $table->string('customer_phone', 40);
            $table->string('fulfillment_type', 30)->default('pickup');
            $table->string('fulfillment_time', 80)->nullable();
            $table->text('address')->nullable();
            $table->text('customer_note')->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->string('shipping_provider', 80)->nullable();
            $table->decimal('shipping_cost', 15, 2)->default(0);
            $table->decimal('shipping_discount', 15, 2)->default(0);
            $table->decimal('extra_fee', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);
            $table->string('status', 40)->default('menunggu_ongkir');
            $table->string('admin_token', 80);
            $table->timestampTz('token_expires_at')->nullable();
            $table->timestampTz('shipping_filled_at')->nullable();
            $table->timestampTz('payment_message_sent_at')->nullable();
            $table->timestampTz('payment_verified_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'order_code']);
            $table->unique(['tenant_id', 'admin_token']);
            $table->index(['tenant_id', 'status', 'created_at']);
            $table->index(['tenant_id', 'customer_phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('online_orders');
    }
};