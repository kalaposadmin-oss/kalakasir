<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lock_periods', function (Blueprint $table) {
            $table->uuid('locked_by')->nullable()->after('is_locked');
            $table->timestampTz('locked_at')->nullable()->after('locked_by');
            $table->string('reason')->nullable()->after('locked_at');
            $table->unique(['tenant_id', 'start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::table('lock_periods', function (Blueprint $table) {
            $table->dropUnique(['lock_periods_tenant_id_start_date_end_date_unique']);
            $table->dropColumn(['locked_by', 'locked_at', 'reason']);
        });
    }
};
