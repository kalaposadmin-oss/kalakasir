<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            if (! Schema::hasColumn('purchases', 'subtotal')) {
                $table->decimal('subtotal', 15, 2)->default(0)->after('supplier_id');
            }

            if (! Schema::hasColumn('purchases', 'tax_rate')) {
                $table->decimal('tax_rate', 8, 4)->default(0)->after('subtotal');
            }

            if (! Schema::hasColumn('purchases', 'tax')) {
                $table->decimal('tax', 15, 2)->default(0)->after('tax_rate');
            }
        });

        if (Schema::hasColumn('purchases', 'subtotal')) {
            DB::table('purchases')
                ->where(function ($query) {
                    $query->whereNull('subtotal')
                        ->orWhere('subtotal', 0);
                })
                ->update([
                    'subtotal' => DB::raw('total'),
                    'tax_rate' => 0,
                    'tax' => 0,
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            if (Schema::hasColumn('purchases', 'tax')) {
                $table->dropColumn('tax');
            }

            if (Schema::hasColumn('purchases', 'tax_rate')) {
                $table->dropColumn('tax_rate');
            }

            if (Schema::hasColumn('purchases', 'subtotal')) {
                $table->dropColumn('subtotal');
            }
        });
    }
};
