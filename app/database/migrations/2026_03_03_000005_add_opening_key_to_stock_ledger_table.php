<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_ledger', function (Blueprint $table) {
            if (! Schema::hasColumn('stock_ledger', 'opening_key')) {
                $table->string('opening_key')->nullable()->after('reference_type');
            }
        });

        DB::table('stock_ledger')
            ->where('reference_type', 'initial_stock')
            ->whereNull('opening_key')
            ->update(['opening_key' => 'OPENING']);

        Schema::table('stock_ledger', function (Blueprint $table) {
            $table->unique(
                ['tenant_id', 'product_id', 'location_id', 'opening_key'],
                'stock_ledger_opening_key_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('stock_ledger', function (Blueprint $table) {
            $table->dropUnique('stock_ledger_opening_key_unique');

            if (Schema::hasColumn('stock_ledger', 'opening_key')) {
                $table->dropColumn('opening_key');
            }
        });
    }
};
