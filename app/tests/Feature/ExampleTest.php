<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $route = Route::getRoutes()->match(\Illuminate\Http\Request::create('/up', 'GET'));

        $this->assertNotNull($route);
        $this->assertSame('up', $route->uri());
    }
}
