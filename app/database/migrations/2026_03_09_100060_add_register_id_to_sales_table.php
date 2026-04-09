<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->foreignUuid('register_id')->nullable()->constrained('registers')->restrictOnDelete();
            $table->index(['tenant_id', 'register_id']);
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'register_id']);
            $table->dropConstrainedForeignId('register_id');
        });
    }
};
