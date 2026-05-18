<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_saved_orders', function (Blueprint $table): void {
            $table->foreignUuid('location_id')
                ->nullable()
                ->after('branch_id')
                ->constrained('locations')
                ->nullOnDelete();

            $table->index(['tenant_id', 'location_id'], 'pos_saved_orders_tenant_location_idx');
        });
    }

    public function down(): void
    {
        Schema::table('pos_saved_orders', function (Blueprint $table): void {
            $table->dropIndex('pos_saved_orders_tenant_location_idx');
            $table->dropConstrainedForeignId('location_id');
        });
    }
};
