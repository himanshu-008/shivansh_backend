<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_ping_endpoint_returns_ok_status(): void
    {
        $response = $this->getJson('/api/ping');

        $response
            ->assertOk()
            ->assertJson([
                'status' => 'ok',
            ]);
    }

    public function test_seeded_classes_endpoint_returns_data(): void
    {
        $this->seed();

        $response = $this->getJson('/api/classes');

        $response
            ->assertOk()
            ->assertJsonFragment([
                'name' => 'Nursery',
            ]);
    }
}
