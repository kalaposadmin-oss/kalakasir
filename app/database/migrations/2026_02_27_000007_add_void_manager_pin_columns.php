<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('manager_pin_hash')->nullable()->after('password');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->uuid('void_approved_by')->nullable()->after('voided_by');
            $table->index(['tenant_id', 'void_approved_by'], 'sales_tenant_void_approved_by_idx');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex('sales_tenant_void_approved_by_idx');
            $table->dropColumn('void_approved_by');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('manager_pin_hash');
        });
    }
};
