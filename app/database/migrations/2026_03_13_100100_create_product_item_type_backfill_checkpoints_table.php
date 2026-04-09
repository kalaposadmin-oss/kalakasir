<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_item_type_backfill_checkpoints', function (Blueprint $table) {
            $table->id();
            $table->string('scope_key', 80)->unique();
            $table->uuid('tenant_id')->nullable();
            $table->timestampTz('last_created_at')->nullable();
            $table->uuid('last_id')->nullable();
            $table->unsignedBigInteger('processed_rows')->default(0);
            $table->string('status', 20)->default('pending');
            $table->timestampsTz();

            $table->index(['tenant_id']);
            $table->index(['last_created_at', 'last_id'], 'product_item_type_backfill_cursor_idx');
        });
    }

    public function down(): void
    {
        Schema::drop('product_item_type_backfill_checkpoints');
    }
};
