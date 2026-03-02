<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->string('payment_method', 20)->default('tempo')->after('status');
            $table->date('due_date')->nullable()->after('payment_method');
            $table->index(['tenant_id', 'payment_method'], 'purchases_tenant_payment_method_idx');
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropIndex('purchases_tenant_payment_method_idx');
            $table->dropColumn(['payment_method', 'due_date']);
        });
    }
};
