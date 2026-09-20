<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DatabaseSnapshotCommandTest extends TestCase
{
    protected string $fakeDump;
    protected string $snapshot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeDump = storage_path('framework/testing/fake-mysqldump-'.uniqid('', true).'.sh');
        $this->snapshot = storage_path('framework/testing/database-snapshot-'.uniqid('', true).'.sql');

        File::ensureDirectoryExists(dirname($this->fakeDump));

        File::put($this->fakeDump, <<<'SH'
#!/bin/sh
set -eu
output=""
for arg in "$@"; do
    case "$arg" in
        --result-file=*) output="${arg#--result-file=}" ;;
    esac
done
[ -n "$output" ]
printf '%s\n' '-- fake database snapshot' 'CREATE TABLE test_snapshot (id INT);' > "$output"
SH
        );

        chmod($this->fakeDump, 0700);

        config([
            'deploy.database_backup.binary' => $this->fakeDump,
            'deploy.database_backup.timeout_seconds' => 30,
        ]);
    }

    protected function tearDown(): void
    {
        @unlink($this->fakeDump);
        @unlink($this->snapshot);

        parent::tearDown();
    }

    public function test_snapshot_command_creates_non_empty_sql_file(): void
    {
        $exitCode = Artisan::call('database:snapshot', [
            'path' => $this->snapshot,
        ]);

        $this->assertSame(0, $exitCode, Artisan::output());
        $this->assertFileExists($this->snapshot);
        $this->assertGreaterThan(0, filesize($this->snapshot));
        $this->assertStringContainsString('CREATE TABLE test_snapshot', File::get($this->snapshot));
    }

    public function test_snapshot_command_does_not_overwrite_existing_file_without_force(): void
    {
        File::put($this->snapshot, 'existing snapshot');

        $exitCode = Artisan::call('database:snapshot', [
            'path' => $this->snapshot,
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertSame('existing snapshot', File::get($this->snapshot));
    }
}
