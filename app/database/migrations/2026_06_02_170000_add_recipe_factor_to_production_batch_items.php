<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_batch_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('production_batch_items', 'recipe_factor')) {
                $table->decimal('recipe_factor', 15, 4)->nullable()->after('uom');
            }
        });
    }

    public function down(): void
    {
        Schema::table('production_batch_items', function (Blueprint $table): void {
            if (Schema::hasColumn('production_batch_items', 'recipe_factor')) {
                $table->dropColumn('recipe_factor');
            }
        });
    }
};
