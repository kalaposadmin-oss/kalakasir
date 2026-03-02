<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->foreignUuid('location_id')->nullable()->after('supplier_id')->constrained('locations');
            $table->timestampTz('approved_at')->nullable()->after('notes');
            $table->uuid('approved_by')->nullable()->after('approved_at');
            $table->timestampTz('received_at')->nullable()->after('approved_by');
            $table->uuid('received_by')->nullable()->after('received_at');
            $table->timestampTz('closed_at')->nullable()->after('received_by');
            $table->uuid('closed_by')->nullable()->after('closed_at');
            $table->timestampTz('cancelled_at')->nullable()->after('closed_by');
            $table->uuid('cancelled_by')->nullable()->after('cancelled_at');
        });

        Schema::table('purchase_items', function (Blueprint $table) {
            $table->decimal('received_qty', 15, 2)->default(0)->after('qty');
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('location_id');
            $table->dropColumn([
                'approved_at',
                'approved_by',
                'received_at',
                'received_by',
                'closed_at',
                'closed_by',
                'cancelled_at',
                'cancelled_by',
            ]);
        });

        Schema::table('purchase_items', function (Blueprint $table) {
            $table->dropColumn('received_qty');
        });
    }
};
