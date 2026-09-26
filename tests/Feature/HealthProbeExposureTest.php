<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthProbeExposureTest extends TestCase
{
    public function test_public_health_endpoints_return_only_the_minimal_probe_contract(): void
    {
        $this->getJson('/ping')->assertOk()->assertExactJson([
            'ok' => true,
            'message' => 'web pong',
        ]);

        $this->getJson('/api/ping')->assertOk()->assertExactJson([
            'ok' => true,
            'message' => 'api pong',
        ]);
    }
}
