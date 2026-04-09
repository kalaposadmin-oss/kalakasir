<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_ledger', function (Blueprint $table) {
            if (! Schema::hasColumn('stock_ledger', 'unit_cost')) {
                $table->decimal('unit_cost', 15, 2)->default(0)->after('balance_after');
            }
            if (! Schema::hasColumn('stock_ledger', 'total_cost')) {
                $table->decimal('total_cost', 15, 2)->default(0)->after('unit_cost');
            }
        });
    }

    public function down(): void
    {
        Schema::table('stock_ledger', function (Blueprint $table) {
            if (Schema::hasColumn('stock_ledger', 'total_cost')) {
                $table->dropColumn('total_cost');
            }
            if (Schema::hasColumn('stock_ledger', 'unit_cost')) {
                $table->dropColumn('unit_cost');
            }
        });
    }
};
