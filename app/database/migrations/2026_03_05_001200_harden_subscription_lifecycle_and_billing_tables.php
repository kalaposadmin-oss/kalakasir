<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('subscriptions')) {
            Schema::table('subscriptions', function (Blueprint $table): void {
                if (! Schema::hasColumn('subscriptions', 'current_period_start')) {
                    $table->timestampTz('current_period_start')->nullable()->after('started_at');
                }
                if (! Schema::hasColumn('subscriptions', 'current_period_end')) {
                    $table->timestampTz('current_period_end')->nullable()->after('current_period_start');
                }
                if (! Schema::hasColumn('subscriptions', 'trial_ends_at')) {
                    $table->timestampTz('trial_ends_at')->nullable()->after('current_period_end');
                }
                if (! Schema::hasColumn('subscriptions', 'grace_ends_at')) {
                    $table->timestampTz('grace_ends_at')->nullable()->after('trial_ends_at');
                }
                if (! Schema::hasColumn('subscriptions', 'canceled_at')) {
                    $table->timestampTz('canceled_at')->nullable()->after('grace_ends_at');
                }
                if (! Schema::hasColumn('subscriptions', 'is_current')) {
                    $table->boolean('is_current')->default(false)->after('expires_at');
                }
                if (! Schema::hasColumn('subscriptions', 'created_at')) {
                    $table->timestampTz('created_at')->nullable();
                }
                if (! Schema::hasColumn('subscriptions', 'updated_at')) {
                    $table->timestampTz('updated_at')->nullable();
                }
            });

            DB::table('subscriptions')->whereNull('status')->update(['status' => 'active']);
            DB::table('subscriptions')->whereNull('created_at')->update(['created_at' => now('UTC')]);
            DB::table('subscriptions')->whereNull('updated_at')->update(['updated_at' => now('UTC')]);
            DB::table('subscriptions')->whereNull('current_period_start')->update(['current_period_start' => DB::raw('started_at')]);
            DB::table('subscriptions')->whereNull('current_period_end')->update(['current_period_end' => DB::raw('COALESCE(expires_at, started_at)')]);

            $tenantIds = DB::table('subscriptions')->distinct()->pluck('tenant_id');
            foreach ($tenantIds as $tenantId) {
                $currentId = DB::table('subscriptions')
                    ->where('tenant_id', $tenantId)
                    ->orderByDesc('started_at')
                    ->value('id');

                if ($currentId) {
                    DB::table('subscriptions')
                        ->where('tenant_id', $tenantId)
                        ->update(['is_current' => false]);

                    DB::table('subscriptions')
                        ->where('id', $currentId)
                        ->update(['is_current' => true]);
                }
            }
        }

        if (Schema::hasTable('subscription_audit_logs')) {
            Schema::table('subscription_audit_logs', function (Blueprint $table): void {
                if (! Schema::hasColumn('subscription_audit_logs', 'request_id')) {
                    $table->string('request_id', 64)->nullable()->after('user_agent');
                }
                if (! Schema::hasColumn('subscription_audit_logs', 'actor_role')) {
                    $table->string('actor_role', 40)->nullable()->after('request_id');
                }
                if (! Schema::hasColumn('subscription_audit_logs', 'source')) {
                    $table->string('source', 30)->default('api')->after('actor_role');
                }
                $table->index(['tenant_id', 'source', 'created_at'], 'subscription_audit_logs_tenant_source_created_idx');
            });
        }

        if (! Schema::hasTable('invoices')) {
            Schema::create('invoices', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('tenant_id')->constrained('tenants');
                $table->string('number', 40);
                $table->date('period_start');
                $table->date('period_end');
                $table->decimal('amount_due', 14, 2)->default(0);
                $table->decimal('amount_paid', 14, 2)->default(0);
                $table->string('status', 30)->default('unpaid');
                $table->timestampTz('issued_at')->nullable();
                $table->timestampTz('due_at')->nullable();
                $table->timestampTz('paid_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestampTz('created_at')->nullable();
                $table->timestampTz('updated_at')->nullable();

                $table->unique(['tenant_id', 'number']);
                $table->index(['tenant_id', 'status', 'period_end']);
            });
        }

        if (! Schema::hasTable('invoice_items')) {
            Schema::create('invoice_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignUuid('invoice_id')->constrained('invoices')->cascadeOnDelete();
                $table->string('description', 255);
                $table->decimal('qty', 14, 2)->default(1);
                $table->decimal('unit_price', 14, 2)->default(0);
                $table->decimal('total_price', 14, 2)->default(0);
                $table->timestampTz('created_at')->nullable();
                $table->timestampTz('updated_at')->nullable();
            });
        }

        if (! Schema::hasTable('subscription_payments')) {
            Schema::create('subscription_payments', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('tenant_id')->constrained('tenants');
                $table->foreignUuid('invoice_id')->nullable()->constrained('invoices');
                $table->string('provider', 40)->default('manual');
                $table->string('reference', 100);
                $table->string('status', 30)->default('paid');
                $table->decimal('amount', 14, 2)->default(0);
                $table->timestampTz('paid_at')->nullable();
                $table->string('idempotency_key', 120)->nullable();
                $table->json('payload')->nullable();
                $table->timestampTz('created_at')->nullable();
                $table->timestampTz('updated_at')->nullable();

                $table->unique(['tenant_id', 'provider', 'reference']);
                $table->unique(['tenant_id', 'idempotency_key']);
                $table->index(['tenant_id', 'status', 'paid_at']);
            });
        }

        if (! Schema::hasTable('subscription_operations')) {
            Schema::create('subscription_operations', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('tenant_id')->constrained('tenants');
                $table->string('operation', 60);
                $table->string('idempotency_key', 120);
                $table->longText('response_payload');
                $table->timestampTz('created_at');

                $table->unique(['tenant_id', 'operation', 'idempotency_key'], 'subscription_operation_idempotent_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_operations');
        Schema::dropIfExists('subscription_payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};
