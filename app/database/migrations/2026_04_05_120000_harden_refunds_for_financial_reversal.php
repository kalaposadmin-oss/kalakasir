<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->foreignUuid('register_id')->nullable()->after('sale_id')->constrained('registers')->restrictOnDelete();
            $table->foreignUuid('shift_id')->nullable()->after('register_id')->constrained('shifts')->restrictOnDelete();
            $table->decimal('subtotal', 15, 2)->default(0)->after('created_by');
            $table->decimal('item_discount_total', 15, 2)->default(0)->after('subtotal');
            $table->decimal('transaction_discount_total', 15, 2)->default(0)->after('item_discount_total');
            $table->decimal('tax_total', 15, 2)->default(0)->after('transaction_discount_total');
            $table->decimal('refunded_cost_total', 15, 2)->default(0)->after('tax_total');
            $table->decimal('payout_total', 15, 2)->default(0)->after('refunded_cost_total');

            $table->index(['tenant_id', 'register_id']);
            $table->index(['tenant_id', 'shift_id']);
        });

        Schema::table('refund_items', function (Blueprint $table) {
            $table->foreignUuid('sale_item_id')->nullable()->after('refund_id')->constrained('sale_items')->restrictOnDelete();
            $table->foreignUuid('product_variant_id')->nullable()->after('product_id')->constrained('product_variants')->restrictOnDelete();
            $table->foreignUuid('location_id')->nullable()->after('product_variant_id')->constrained('locations')->restrictOnDelete();
            $table->decimal('unit_price_snapshot', 15, 2)->default(0)->after('qty');
            $table->decimal('gross_amount', 15, 2)->default(0)->after('unit_price_snapshot');
            $table->decimal('item_discount_amount', 15, 2)->default(0)->after('gross_amount');
            $table->decimal('transaction_discount_amount', 15, 2)->default(0)->after('item_discount_amount');
            $table->decimal('tax_amount', 15, 2)->default(0)->after('transaction_discount_amount');
            $table->decimal('net_amount', 15, 2)->default(0)->after('tax_amount');
            $table->decimal('hpp_snapshot', 15, 2)->default(0)->after('net_amount');
            $table->decimal('refunded_cost_total', 15, 2)->default(0)->after('hpp_snapshot');

            $table->index(['refund_id', 'sale_item_id']);
        });

        Schema::table('inventory_issue_allocations', function (Blueprint $table) {
            $table->decimal('reversed_qty', 15, 2)->default(0)->after('qty');
            $table->index(['tenant_id', 'reference_type', 'reference_id', 'reference_item_id'], 'idx_issue_alloc_reference_item');
        });

        Schema::create('refund_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignUuid('refund_id')->constrained('refunds')->restrictOnDelete();
            $table->foreignUuid('sale_payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->uuid('account_id')->nullable()->index();
            $table->foreignUuid('payment_method_id')->nullable()->constrained('payment_methods')->nullOnDelete();
            $table->string('method');
            $table->decimal('amount', 15, 2);
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['tenant_id', 'refund_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_payments');

        Schema::table('inventory_issue_allocations', function (Blueprint $table) {
            $table->dropIndex('idx_issue_alloc_reference_item');
            $table->dropColumn('reversed_qty');
        });

        Schema::table('refund_items', function (Blueprint $table) {
            $table->dropIndex(['refund_id', 'sale_item_id']);
            $table->dropConstrainedForeignId('sale_item_id');
            $table->dropConstrainedForeignId('product_variant_id');
            $table->dropConstrainedForeignId('location_id');
            $table->dropColumn([
                'unit_price_snapshot',
                'gross_amount',
                'item_discount_amount',
                'transaction_discount_amount',
                'tax_amount',
                'net_amount',
                'hpp_snapshot',
                'refunded_cost_total',
            ]);
        });

        Schema::table('refunds', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'register_id']);
            $table->dropIndex(['tenant_id', 'shift_id']);
            $table->dropConstrainedForeignId('register_id');
            $table->dropConstrainedForeignId('shift_id');
            $table->dropColumn([
                'subtotal',
                'item_discount_total',
                'transaction_discount_total',
                'tax_total',
                'refunded_cost_total',
                'payout_total',
            ]);
        });
    }
};