<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('bank_name', 120)->nullable();
            $table->string('bank_account_number', 120)->nullable();
            $table->string('bank_account_name', 150)->nullable();
            $table->string('admin_whatsapp_number', 30)->nullable();
            $table->text('payment_note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_settings');
    }
};