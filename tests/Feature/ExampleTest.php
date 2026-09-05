<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        // La app vive bajo /app (proxy del CI); la raíz no tiene ruta. Se usa el health check.
        $response = $this->get('/up');

        $response->assertStatus(200);
    }
}
