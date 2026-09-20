<?php

namespace Tests\Unit;

use Tests\TestCase;

class ServerPreflightScriptTest extends TestCase
{
    public function test_server_preflight_is_read_only_and_checks_release_prerequisites(): void
    {
        $script = file_get_contents(base_path('server-preflight.sh'));

        $this->assertIsString($script);
        $this->assertStringContainsString('umask 077', $script);
        $this->assertStringContainsString('/home/u637857322/domains/tag-marketplace.com/laravel_app', $script);
        $this->assertStringContainsString('/home/u637857322/domains/tag-marketplace.com/public_html', $script);
        $this->assertStringContainsString('mysqldump', $script);
        $this->assertStringContainsString('rsync', $script);
        $this->assertStringContainsString('pdo_mysql', $script);
        $this->assertStringContainsString('artisan migrate:status', $script);
        $this->assertStringContainsString('git -C "$APP_DIR" status --porcelain', $script);
        $this->assertStringContainsString('curl -L -sS', $script);

        $this->assertStringNotContainsString('artisan migrate --force', $script);
        $this->assertStringNotContainsString('git reset --hard', $script);
        $this->assertStringNotContainsString('composer install', $script);
        $this->assertStringNotContainsString('npm install', $script);
        $this->assertStringNotContainsString('npm run build', $script);
    }
}
