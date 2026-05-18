<?php

namespace Tests\Unit;

use Illuminate\Http\Request;
use App\Http\Controllers\Web\UserController;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    #[Test]
    public function it_returns_current_user_data()
    {
        // Create a mock request
        $request = new Request();

        // Mock the authenticated user
        $user = new \App\Models\User([
            'id' => 1,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'branch_id' => 1,
        ]);

        $this->actingAs($user);

        $controller = new UserController();
        $response = $controller->index($request);

        $this->assertInstanceOf(\Illuminate\Http\JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('user', $data);
        $this->assertEquals($user->id, $data['user']['id']);
        $this->assertEquals($user->name, $data['user']['name']);
    }
}
