<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('system_health_events')) {
            Schema::create('system_health_events', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('event_name', 120);
                $table->string('severity', 20);
                $table->uuid('tenant_id')->nullable();
                $table->uuid('branch_id')->nullable();
                $table->string('entity_type', 80)->nullable();
                $table->string('entity_id', 80)->nullable();
                $table->text('summary');
                $table->json('payload_json');
                $table->string('source', 120);
                $table->string('status', 20)->default('open');
                $table->timestampTz('detected_at')->useCurrent();
                $table->timestampTz('first_detected_at')->nullable();
                $table->timestampTz('resolved_at')->nullable();
                $table->timestampTz('last_alerted_at')->nullable();
                $table->unsignedInteger('occurrence_count')->default(1);
                $table->string('dedupe_key', 190)->nullable();
                $table->timestampsTz();

                $table->index(['status', 'severity', 'detected_at'], 'she_status_severity_detected_idx');
                $table->index(['tenant_id', 'status', 'detected_at'], 'she_tenant_status_detected_idx');
                $table->index(['event_name', 'status', 'detected_at'], 'she_name_status_detected_idx');
                $table->index(['entity_type', 'entity_id', 'status'], 'she_entity_status_idx');
                $table->index(['dedupe_key', 'status'], 'she_dedupe_status_idx');
            });
        }

        if (! Schema::hasTable('system_health_event_occurrences')) {
            Schema::create('system_health_event_occurrences', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('system_health_event_id');
                $table->text('summary');
                $table->json('payload_json');
                $table->timestampTz('detected_at')->useCurrent();
                $table->unsignedInteger('occurrence_number')->default(1);
                $table->timestampsTz();

                $table->foreign('system_health_event_id', 'sheo_event_fk')
                    ->references('id')
                    ->on('system_health_events')
                    ->cascadeOnDelete();

                $table->index(['system_health_event_id', 'detected_at'], 'sheo_event_detected_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('system_health_event_occurrences');
        Schema::dropIfExists('system_health_events');
    }
};