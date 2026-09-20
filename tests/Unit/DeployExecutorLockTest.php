<?php

namespace Tests\Unit;

use App\Services\System\DeployExecutorService;
use App\Services\System\DeployRemoteRequest;
use Tests\TestCase;

class DeployExecutorLockTest extends TestCase
{
    protected string $lockPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lockPath = storage_path('framework/testing/deploy-lock-'.uniqid('', true).'.lock');
        config(['deploy.lock_file' => $this->lockPath]);
    }

    protected function tearDown(): void
    {
        @unlink($this->lockPath);

        parent::tearDown();
    }

    public function test_only_one_execution_can_acquire_the_lock_and_only_owner_can_clear_it(): void
    {
        $service = new TestableDeployExecutorService(new DeployRemoteRequest());

        $this->assertTrue($service->acquireForTest('deploy', 'token-one'));
        $this->assertFalse($service->acquireForTest('rollback', 'token-two'));

        $service->clearForTest('wrong-token');
        $this->assertFalse($service->acquireForTest('rollback', 'token-two'));

        $service->clearForTest('token-one');
        $this->assertTrue($service->acquireForTest('rollback', 'token-two'));

        $service->clearForTest('token-two');
        $this->assertFileDoesNotExist($this->lockPath);
    }
}

class TestableDeployExecutorService extends DeployExecutorService
{
    public function acquireForTest(string $action, string $token): bool
    {
        return $this->acquireLock($action, $token);
    }

    public function clearForTest(string $token): void
    {
        $this->clearLock($token);
    }
}
