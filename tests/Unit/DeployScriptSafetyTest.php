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

    public function test_production_queue_restart_is_not_silently_inherited_by_qas(): void
    {
        $production = file_get_contents(base_path('deploy.sh'));
        $qas = file_get_contents(base_path('deploy-qas.sh'));

        $this->assertIsString($production);
        $this->assertIsString($qas);
        $this->assertStringContainsString('artisan queue:restart', $production);
        $this->assertStringNotContainsString('artisan queue:restart', $qas);
        $this->assertStringContainsString('No npm, Vite build, rsync, chmod, index rewrite, migration, queue restart or backup.', $qas);
    }

    public function test_qas_public_assets_are_immutable_and_verified_before_maintenance(): void
    {
        $script = file_get_contents(base_path('deploy-qas.sh'));

        $this->assertIsString($script);
        $this->assertStringContainsString('BASELINE_PUBLIC_TREE=', $script);
        $this->assertStringContainsString('TARGET_PUBLIC_TREE=', $script);
        $this->assertStringContainsString('[ "$BASELINE_PUBLIC_TREE" = "$TARGET_PUBLIC_TREE" ]', $script);
        $this->assertStringContainsString('cmp -s "$local_asset" "$live_asset"', $script);
        $this->assertStringContainsString('realpath -e "$PUBLIC_DIR/build"', $script);
        $this->assertStringNotContainsString('rsync -a ', $script);
        $this->assertStringNotContainsString('find "$PUBLIC_DIR" ', $script);
        $this->assertStringNotContainsString('sed -i', $script);
        $this->assertStringNotContainsString('npm run build', $script);
    }

    public function test_qas_deploy_requires_mobile_admin_controller_to_be_publicly_readable(): void
    {
        $script = file_get_contents(base_path('deploy-qas.sh'));

        $this->assertIsString($script);
        $this->assertStringContainsString('ASSET_HEALTHCHECK_URL="$HEALTHCHECK_URL/admin/js/off-canvas.js"', $script);
        $this->assertStringContainsString('QAS static asset health check failed', $script);
        $this->assertStringContainsString('QAS static asset health check passed', $script);
    }

    public function test_deploys_protect_upload_execution_guard_without_syncing_user_uploads(): void
    {
        $production = file_get_contents(base_path('deploy.sh'));
        $qas = file_get_contents(base_path('deploy-qas.sh'));
        $guard = file_get_contents(base_path('ops/uploads.htaccess'));

        $this->assertIsString($production);
        $this->assertIsString($qas);
        $this->assertIsString($guard);
        $this->assertStringContainsString("--exclude='uploads'", $production);
        $this->assertStringContainsString('ops/uploads.htaccess', $production);
        $this->assertStringContainsString('ops/uploads.htaccess', $qas);
        $this->assertStringContainsString('cmp -s "$APP_DIR/ops/uploads.htaccess" "$PUBLIC_DIR/uploads/.htaccess"', $qas);
        $this->assertStringContainsString('realpath -e "$PUBLIC_DIR/uploads"', $qas);
        $this->assertStringNotContainsString('rsync -a ', $qas);
        $this->assertStringContainsString('Require all denied', $guard);
        $this->assertStringContainsString('RemoveHandler .php', $guard);
    }


}
