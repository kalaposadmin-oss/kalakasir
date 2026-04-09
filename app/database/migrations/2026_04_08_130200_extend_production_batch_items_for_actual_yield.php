<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('production_batch_items', 'planned_qty')) {
            Schema::table('production_batch_items', function (Blueprint $table) {
                $table->decimal('planned_qty', 15, 4)->default(0)->after('product_variant_id');
            });
        }

        if (! Schema::hasColumn('production_batch_items', 'actual_qty')) {
            Schema::table('production_batch_items', function (Blueprint $table) {
                $table->decimal('actual_qty', 15, 4)->nullable()->after('planned_qty');
            });
        }

        if (! Schema::hasColumn('production_batch_items', 'uom')) {
            Schema::table('production_batch_items', function (Blueprint $table) {
                $table->string('uom', 50)->nullable()->after('actual_qty');
            });
        }

        if (! Schema::hasColumn('production_batch_items', 'created_at')) {
            Schema::table('production_batch_items', function (Blueprint $table) {
                $table->timestampTz('created_at')->nullable()->after('uom');
            });
        }

        if (! Schema::hasColumn('production_batch_items', 'updated_at')) {
            Schema::table('production_batch_items', function (Blueprint $table) {
                $table->timestampTz('updated_at')->nullable()->after('created_at');
            });
        }

        Schema::table('production_batch_items', function (Blueprint $table) {
            $table->index(['production_batch_id', 'line_type'], 'production_batch_items_batch_type_idx_v2');
        });

        DB::table('production_batch_items')->update([
            'planned_qty' => DB::raw('qty'),
        ]);
    }

    public function down(): void
    {
        Schema::table('production_batch_items', function (Blueprint $table) {
            $table->dropIndex('production_batch_items_batch_type_idx_v2');
        });

        $droppableColumns = collect(['planned_qty', 'actual_qty', 'uom', 'created_at', 'updated_at'])
            ->filter(fn (string $column) => Schema::hasColumn('production_batch_items', $column))
            ->values()
            ->all();

        if ($droppableColumns !== []) {
            Schema::table('production_batch_items', function (Blueprint $table) use ($droppableColumns) {
                $table->dropColumn($droppableColumns);
            });
        }
    }
};