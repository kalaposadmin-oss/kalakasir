<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'default_location_id')) {
                $table->foreignUuid('default_location_id')
                    ->nullable()
                    ->constrained('locations')
                    ->nullOnDelete();
            }
        });

        $defaults = DB::table('locations')
            ->where('is_default', true)
            ->orderBy('name')
            ->get(['tenant_id', 'id']);

        foreach ($defaults as $row) {
            DB::table('tenants')
                ->where('id', $row->tenant_id)
                ->whereNull('default_location_id')
                ->update(['default_location_id' => $row->id]);
        }
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'default_location_id')) {
                $table->dropConstrainedForeignId('default_location_id');
            }
        });
    }
};
