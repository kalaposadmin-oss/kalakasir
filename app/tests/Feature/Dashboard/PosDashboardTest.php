<?php

namespace Tests\Feature\Dashboard;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosDashboardTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_returns_pos_dashboard_data()
    {
        $response = $this->getJson('/api/v1/pos/dashboard');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'current_shift',
                    'total_open_registers',
                    'active_registers',
                    'closed_registers'
                ]
            ])
            ->assertJson([
                'data' => [
                    'current_shift' => 'Shift 1 - Active',
                    'total_open_registers' => 0,
                    'active_registers' => 0,
                    'closed_registers' => 0
                ]
            ]);
    }

    /** @test */
    public function it_includes_user_data_in_response()
    {
        // Mock authenticated user
        $this->actingAs($this->createUser());

        $response = $this->getJson('/api/v1/pos/dashboard');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'user'
            ]);
    }
}