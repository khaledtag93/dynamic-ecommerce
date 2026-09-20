<?php

namespace Tests\Unit;

use App\Services\System\DeployRemoteRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DeployRemoteRequestTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'deploy.remote.shared_secret' => 'test-deploy-secret',
            'deploy.remote.max_request_age_seconds' => 300,
        ]);

        Cache::flush();
    }

    public function test_signed_request_is_accepted_once_and_replay_is_rejected(): void
    {
        $service = new DeployRemoteRequest();
        $body = json_encode(['action' => 'deploy', 'action_mode' => 'dry_run'], JSON_UNESCAPED_SLASHES);
        $headers = $service->headers($body, now()->timestamp, 'req-test-001');

        $request = $this->requestWithHeaders($body, $headers);

        $this->assertTrue($service->isValid($request));
        $this->assertFalse($service->isValid($request));
    }

    public function test_tampered_body_does_not_consume_valid_request_id(): void
    {
        $service = new DeployRemoteRequest();
        $body = json_encode(['action' => 'deploy', 'action_mode' => 'dry_run'], JSON_UNESCAPED_SLASHES);
        $headers = $service->headers($body, now()->timestamp, 'req-test-002');

        $tampered = $this->requestWithHeaders(
            json_encode(['action' => 'deploy', 'action_mode' => 'execute'], JSON_UNESCAPED_SLASHES),
            $headers
        );

        $this->assertFalse($service->isValid($tampered));

        $valid = $this->requestWithHeaders($body, $headers);
        $this->assertTrue($service->isValid($valid));
    }

    protected function requestWithHeaders(string $body, array $headers): Request
    {
        $request = Request::create('/api/internal/deploy-center/execute', 'POST', [], [], [], [], $body);

        foreach ($headers as $name => $value) {
            $request->headers->set($name, $value);
        }

        return $request;
    }
}
