<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('customers', 'order_link_token_hash')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->string('order_link_token_hash', 64)->nullable()->unique();
                $table->text('order_link_token_encrypted')->nullable();
                $table->timestampTz('order_link_token_created_at')->nullable();
                $table->timestampTz('order_link_token_rotated_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('customers', 'order_link_token_hash')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn([
                    'order_link_token_hash',
                    'order_link_token_encrypted',
                    'order_link_token_created_at',
                    'order_link_token_rotated_at',
                ]);
            });
        }
    }
};
