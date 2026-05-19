<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_items', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_items', 'purchase_qty')) {
                $table->decimal('purchase_qty', 15, 4)->nullable()->after('received_qty');
            }

            if (! Schema::hasColumn('purchase_items', 'purchase_unit')) {
                $table->string('purchase_unit', 30)->nullable()->after('purchase_qty');
            }

            if (! Schema::hasColumn('purchase_items', 'purchase_unit_multiplier')) {
                $table->decimal('purchase_unit_multiplier', 15, 4)->default(1)->after('purchase_unit');
            }

            if (! Schema::hasColumn('purchase_items', 'purchase_price')) {
                $table->decimal('purchase_price', 15, 2)->nullable()->after('purchase_unit_multiplier');
            }
        });

        DB::table('purchase_items')->whereNull('purchase_qty')->update([
            'purchase_qty' => DB::raw('qty'),
            'purchase_unit_multiplier' => 1,
            'purchase_price' => DB::raw('price'),
        ]);
    }

    public function down(): void
    {
        Schema::table('purchase_items', function (Blueprint $table) {
            $columns = collect([
                'purchase_price',
                'purchase_unit_multiplier',
                'purchase_unit',
                'purchase_qty',
            ])->filter(fn (string $column) => Schema::hasColumn('purchase_items', $column))->values()->all();

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
