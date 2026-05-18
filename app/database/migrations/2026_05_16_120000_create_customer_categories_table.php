<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('slug', 100);
            $table->string('color', 24)->default('slate');
            $table->timestampsTz();

            $table->unique(['tenant_id', 'slug']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->uuid('customer_category_id')->nullable()->after('address')->index();

            $table->foreign('customer_category_id')
                ->references('id')
                ->on('customer_categories')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['customer_category_id']);
            $table->dropColumn('customer_category_id');
        });

        Schema::dropIfExists('customer_categories');
    }
};
