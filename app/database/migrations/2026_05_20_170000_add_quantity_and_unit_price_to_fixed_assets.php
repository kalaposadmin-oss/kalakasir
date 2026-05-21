<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fixed_assets', function (Blueprint $table): void {
            $table->decimal('quantity', 15, 4)->default(1)->after('asset_category');
            $table->decimal('unit_price', 15, 2)->default(0)->after('quantity');
        });

        DB::table('fixed_assets')->update([
            'quantity' => 1,
            'unit_price' => DB::raw('purchase_price'),
        ]);
    }

    public function down(): void
    {
        Schema::table('fixed_assets', function (Blueprint $table): void {
            $table->dropColumn(['quantity', 'unit_price']);
        });
    }
};
