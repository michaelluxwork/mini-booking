<?php

namespace Tests\Feature;

use Tests\TestCase;

class EnvironmentTest extends TestCase
{
    public function test_api_can_connect_to_sqlite(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertJson([
                'status' => 'ok',
                'database' => 'sqlite',
                'timezone' => 'Europe/Moscow',
            ]);
    }
}
