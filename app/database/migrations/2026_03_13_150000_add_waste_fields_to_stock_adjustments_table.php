<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_adjustments', function (Blueprint $table) {
            if (! Schema::hasColumn('stock_adjustments', 'waste_type')) {
                $table->string('waste_type', 30)->nullable()->after('reason_code');
            }

            if (! Schema::hasColumn('stock_adjustments', 'production_batch_id')) {
                $table->foreignUuid('production_batch_id')
                    ->nullable()
                    ->after('waste_type')
                    ->constrained('production_batches');
            }
        });
    }

    public function down(): void
    {
        Schema::table('stock_adjustments', function (Blueprint $table) {
            if (Schema::hasColumn('stock_adjustments', 'production_batch_id')) {
                $table->dropConstrainedForeignId('production_batch_id');
            }

            if (Schema::hasColumn('stock_adjustments', 'waste_type')) {
                $table->dropColumn('waste_type');
            }
        });
    }
};
