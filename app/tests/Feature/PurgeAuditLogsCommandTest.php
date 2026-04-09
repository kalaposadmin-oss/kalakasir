<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PurgeAuditLogsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_purge_requires_force_flag(): void
    {
        $this->artisan('audit:purge')
            ->expectsOutputToContain('Gunakan --force untuk menjalankan purge audit log.')
            ->assertExitCode(2);
    }

    public function test_audit_purge_deletes_only_rows_older_than_retention_window(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Audit Purge',
            'timezone' => 'UTC',
            'plan' => 'M',
        ]);

        $oldAuditId = (string) Str::uuid();
        $newAuditId = (string) Str::uuid();
        $oldSubscriptionAuditId = (string) Str::uuid();
        $newSubscriptionAuditId = (string) Str::uuid();

        DB::table('audit_logs')->insert([
            [
                'id' => $oldAuditId,
                'tenant_id' => $tenant->id,
                'actor_id' => null,
                'event_type' => 'tenant_updated',
                'reference_type' => 'tenant',
                'reference_id' => $tenant->id,
                'payload_json' => json_encode(['scope' => 'old'], JSON_UNESCAPED_SLASHES),
                'created_at' => now('UTC')->subDays(40),
            ],
            [
                'id' => $newAuditId,
                'tenant_id' => $tenant->id,
                'actor_id' => null,
                'event_type' => 'tenant_updated',
                'reference_type' => 'tenant',
                'reference_id' => $tenant->id,
                'payload_json' => json_encode(['scope' => 'recent'], JSON_UNESCAPED_SLASHES),
                'created_at' => now('UTC')->subDays(5),
            ],
        ]);

        DB::table('subscription_audit_logs')->insert([
            [
                'id' => $oldSubscriptionAuditId,
                'tenant_id' => $tenant->id,
                'actor_id' => null,
                'old_plan' => 'S',
                'new_plan' => 'M',
                'reason' => 'Old audit row',
                'ip_address' => '127.0.0.1',
                'user_agent' => 'phpunit',
                'request_id' => 'req-old',
                'actor_role' => 'system',
                'source' => 'command',
                'change_mode' => 'immediate',
                'effective_at' => now('UTC')->subDays(40),
                'proration_amount' => 0,
                'created_at' => now('UTC')->subDays(40),
            ],
            [
                'id' => $newSubscriptionAuditId,
                'tenant_id' => $tenant->id,
                'actor_id' => null,
                'old_plan' => 'M',
                'new_plan' => 'P',
                'reason' => 'Recent audit row',
                'ip_address' => '127.0.0.1',
                'user_agent' => 'phpunit',
                'request_id' => 'req-new',
                'actor_role' => 'system',
                'source' => 'command',
                'change_mode' => 'immediate',
                'effective_at' => now('UTC')->subDays(5),
                'proration_amount' => 0,
                'created_at' => now('UTC')->subDays(5),
            ],
        ]);

        $this->artisan('audit:purge', [
            '--days' => 30,
            '--force' => true,
        ])->expectsOutputToContain('Purge audit log selesai.')
            ->expectsOutputToContain('Audit logs deleted: 1')
            ->expectsOutputToContain('Subscription audit logs deleted: 1')
            ->expectsOutputToContain('Retention days: 30')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('audit_logs', ['id' => $oldAuditId]);
        $this->assertDatabaseHas('audit_logs', ['id' => $newAuditId]);
        $this->assertDatabaseMissing('subscription_audit_logs', ['id' => $oldSubscriptionAuditId]);
        $this->assertDatabaseHas('subscription_audit_logs', ['id' => $newSubscriptionAuditId]);
    }
}