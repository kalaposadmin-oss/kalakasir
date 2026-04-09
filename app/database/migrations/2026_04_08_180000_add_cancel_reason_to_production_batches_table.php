<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_batches', function (Blueprint $table): void {
            if (! Schema::hasColumn('production_batches', 'cancel_reason')) {
                $table->text('cancel_reason')->nullable()->after('variance_reason');
            }
        });
    }

    public function down(): void
    {
        Schema::table('production_batches', function (Blueprint $table): void {
            if (Schema::hasColumn('production_batches', 'cancel_reason')) {
                $table->dropColumn('cancel_reason');
            }
        });
    }
};