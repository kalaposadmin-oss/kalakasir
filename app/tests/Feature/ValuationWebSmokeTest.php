<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ValuationWebSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_valuation_settings_page_renders_core_sections(): void
    {
        $response = $this->get('/settings/inventory-valuation');

        $response->assertStatus(200)
            ->assertSee('Valuation Persediaan')
            ->assertSee('btn-rebuild')
            ->assertSee('btn-export')
            ->assertSee('btn-rebuild-retry')
            ->assertSee('btn-export-retry');
    }

    public function test_inventory_valuation_audit_page_renders_core_sections(): void
    {
        $response = $this->get('/settings/inventory-valuation-audit');

        $response->assertStatus(200)
            ->assertSee('Audit Valuation')
            ->assertSee('btn-refresh')
            ->assertSee('log-list');
    }

    public function test_settings_menu_contains_valuation_entries(): void
    {
        $response = $this->get('/menu/settings');

        $response->assertStatus(200)
            ->assertSee('Valuation Persediaan')
            ->assertSee('Audit Valuation');
    }
}
