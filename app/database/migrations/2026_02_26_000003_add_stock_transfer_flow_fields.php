<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->foreignUuid('from_location_id')->nullable()->after('from_branch_id')->constrained('locations');
            $table->foreignUuid('to_location_id')->nullable()->after('to_branch_id')->constrained('locations');
            $table->timestampTz('approved_at')->nullable()->after('created_at');
            $table->uuid('approved_by')->nullable()->after('approved_at');
            $table->timestampTz('shipped_at')->nullable()->after('approved_by');
            $table->uuid('shipped_by')->nullable()->after('shipped_at');
            $table->timestampTz('received_at')->nullable()->after('shipped_by');
            $table->uuid('received_by')->nullable()->after('received_at');
            $table->timestampTz('closed_at')->nullable()->after('received_by');
            $table->uuid('closed_by')->nullable()->after('closed_at');
            $table->timestampTz('cancelled_at')->nullable()->after('closed_by');
            $table->uuid('cancelled_by')->nullable()->after('cancelled_at');
        });
    }

    public function down(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('from_location_id');
            $table->dropConstrainedForeignId('to_location_id');
            $table->dropColumn([
                'approved_at',
                'approved_by',
                'shipped_at',
                'shipped_by',
                'received_at',
                'received_by',
                'closed_at',
                'closed_by',
                'cancelled_at',
                'cancelled_by',
            ]);
        });
    }
};
