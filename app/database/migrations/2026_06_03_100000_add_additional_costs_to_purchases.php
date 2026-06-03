<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table): void {
            if (! Schema::hasColumn('purchases', 'additional_cost_total')) {
                $column = $table->decimal('additional_cost_total', 15, 2)->default(0);
                if (Schema::hasColumn('purchases', 'tax')) {
                    $column->after('tax');
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table): void {
            if (Schema::hasColumn('purchases', 'additional_cost_total')) {
                $table->dropColumn('additional_cost_total');
            }
        });
    }
};
