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
        $this->assertStringContainsString('_$$.backup', $script);
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
        $this->assertStringContainsString('_$$.backup', $script);
    }

    public function test_qas_deploy_fetches_the_requested_branch_even_when_origin_refspec_is_narrow(): void
    {
        $script = file_get_contents(base_path('deploy-qas.sh'));

        $this->assertIsString($script);
        $this->assertStringContainsString('git fetch --prune origin "+refs/heads/$BRANCH:refs/remotes/origin/$BRANCH"', $script);
        $this->assertStringContainsString('git rev-parse "origin/$BRANCH"', $script);
    }

    public function test_deploys_signal_queue_workers_to_reload_code(): void
    {
        foreach (['deploy.sh', 'deploy-qas.sh'] as $path) {
            $script = file_get_contents(base_path($path));

            $this->assertIsString($script);
            $this->assertStringContainsString('artisan queue:restart', $script);
        }
    }

    public function test_qas_public_sync_normalizes_web_asset_permissions(): void
    {
        $script = file_get_contents(base_path('deploy-qas.sh'));

        $this->assertIsString($script);
        $this->assertStringContainsString('rsync -a --delete --chmod=D755,F644', $script);
        $this->assertStringContainsString("--exclude='uploads'", $script);
    }

    public function test_deploys_install_upload_execution_guard_without_syncing_user_uploads(): void
    {
        $production = file_get_contents(base_path('deploy.sh'));
        $qas = file_get_contents(base_path('deploy-qas.sh'));
        $guard = file_get_contents(base_path('ops/uploads.htaccess'));

        $this->assertIsString($production);
        $this->assertIsString($qas);
        $this->assertIsString($guard);

        foreach ([$production, $qas] as $script) {
            $this->assertStringContainsString("--exclude='uploads'", $script);
            $this->assertStringContainsString('ops/uploads.htaccess', $script);
            $this->assertStringContainsString('$PUBLIC_DIR/uploads/.htaccess', $script);
        }

        $this->assertStringContainsString('Require all denied', $guard);
        $this->assertStringContainsString('RemoveHandler .php', $guard);
    }
}
