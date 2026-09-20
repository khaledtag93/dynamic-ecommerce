<?php

namespace Tests\Unit;

use Tests\TestCase;

class DeployScriptSafetyTest extends TestCase
{
    public function test_deploy_backup_excludes_runtime_env_and_uses_private_umask(): void
    {
        $script = file_get_contents(base_path('deploy.sh'));

        $this->assertIsString($script);
        $this->assertStringContainsString('umask 077', $script);
        $this->assertStringContainsString("--exclude='.env'", $script);
        $this->assertStringContainsString('chmod 600 "$CURRENT_BACKUP_DIR/meta/.env.backup"', $script);
    }

    public function test_manual_rollback_uses_maintenance_mode_and_health_check(): void
    {
        $script = file_get_contents(base_path('rollback.sh'));

        $this->assertIsString($script);
        $this->assertStringContainsString('umask 077', $script);
        $this->assertStringContainsString('artisan down --retry=60', $script);
        $this->assertStringContainsString('health_check', $script);
        $this->assertStringContainsString('artisan up', $script);
        $this->assertStringContainsString('curl -L -sS', $script);
    }
}
