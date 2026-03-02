<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('valuation_audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants');
            $table->uuid('actor_id')->nullable();
            $table->string('action', 120);
            $table->uuid('request_id')->nullable();
            $table->json('meta_json')->nullable();
            $table->string('ip_address', 60)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at');

            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'action', 'created_at']);
            $table->index(['tenant_id', 'request_id']);
        });

        Schema::table('inventory_cost_states', function (Blueprint $table) {
            $table->index(['tenant_id', 'average_cost'], 'ics_tenant_avg_cost_idx');
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->index(['tenant_id', 'hpp_snapshot'], 'sale_items_tenant_hpp_idx');
        });

        Schema::table('inventory_balances', function (Blueprint $table) {
            $table->index(['tenant_id', 'product_id', 'location_id'], 'inv_balances_tenant_product_location_idx');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_balances', function (Blueprint $table) {
            $table->dropIndex('inv_balances_tenant_product_location_idx');
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropIndex('sale_items_tenant_hpp_idx');
        });

        Schema::table('inventory_cost_states', function (Blueprint $table) {
            $table->dropIndex('ics_tenant_avg_cost_idx');
        });

        Schema::dropIfExists('valuation_audit_logs');
    }
};
