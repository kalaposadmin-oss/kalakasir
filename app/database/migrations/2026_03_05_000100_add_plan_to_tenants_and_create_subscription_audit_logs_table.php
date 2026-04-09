<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tenants', 'plan')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->string('plan', 1)->default('S')->after('logo_url');
            });
        }

        DB::table('tenants')
            ->whereNull('plan')
            ->orWhereNotIn('plan', ['S', 'M', 'P'])
            ->update(['plan' => 'S']);

        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE tenants MODIFY plan VARCHAR(1) NOT NULL DEFAULT 'S'");
        }

        if (! Schema::hasTable('subscription_audit_logs')) {
            Schema::create('subscription_audit_logs', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('tenant_id')->constrained('tenants');
                $table->uuid('actor_id')->nullable();
                $table->string('old_plan', 1);
                $table->string('new_plan', 1);
                $table->string('reason', 500);
                $table->string('ip_address', 60)->nullable();
                $table->string('user_agent', 500)->nullable();
                $table->timestamp('created_at');

                $table->index(['tenant_id', 'created_at']);
                $table->index(['tenant_id', 'old_plan', 'new_plan']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_audit_logs');

        if (Schema::hasColumn('tenants', 'plan')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->dropColumn('plan');
            });
        }
    }
};
