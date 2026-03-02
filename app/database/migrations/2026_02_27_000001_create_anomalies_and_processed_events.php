<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anomalies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants');
            $table->string('severity');
            $table->string('type');
            $table->uuid('reference_id')->nullable();
            $table->json('payload_json');
            $table->timestampTz('detected_at')->useCurrent();
            $table->timestampTz('resolved_at')->nullable();
            $table->uuid('resolved_by')->nullable();

            $table->index(['tenant_id', 'type', 'detected_at']);
            $table->index(['tenant_id', 'severity', 'detected_at']);
        });

        Schema::create('processed_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants');
            $table->uuid('event_uuid');
            $table->string('listener_name');
            $table->timestampTz('processed_at')->useCurrent();

            $table->unique(['tenant_id', 'event_uuid', 'listener_name'], 'processed_events_dedupe_unique');
            $table->index(['tenant_id', 'processed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processed_events');
        Schema::dropIfExists('anomalies');
    }
};
