<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'unit')) {
                $table->string('unit', 30)->nullable()->after('hpp_average');
            }

            if (! Schema::hasColumn('products', 'supplier_id')) {
                $table->foreignUuid('supplier_id')->nullable()->after('unit')->constrained('suppliers');
            }

            if (! Schema::hasColumn('products', 'legacy_material_id')) {
                $table->uuid('legacy_material_id')->nullable()->after('supplier_id');
                $table->unique('legacy_material_id', 'products_legacy_material_unique');
            }

            $table->index(['tenant_id', 'supplier_id'], 'products_tenant_supplier_idx');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_tenant_supplier_idx');

            if (Schema::hasColumn('products', 'legacy_material_id')) {
                $table->dropUnique('products_legacy_material_unique');
                $table->dropColumn('legacy_material_id');
            }

            if (Schema::hasColumn('products', 'supplier_id')) {
                $table->dropConstrainedForeignId('supplier_id');
            }

            if (Schema::hasColumn('products', 'unit')) {
                $table->dropColumn('unit');
            }
        });
    }
};