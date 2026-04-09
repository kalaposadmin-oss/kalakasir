<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->decimal('unit_cost', 15, 2)->default(0)->after('unit');
            $table->foreignUuid('supplier_id')->nullable()->after('unit_cost')->constrained('suppliers');
            $table->index(['tenant_id', 'supplier_id']);
        });
    }

    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'supplier_id']);
            $table->dropConstrainedForeignId('supplier_id');
            $table->dropColumn('unit_cost');
        });
    }
};
