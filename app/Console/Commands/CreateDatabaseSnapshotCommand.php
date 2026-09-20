<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class CreateDatabaseSnapshotCommand extends Command
{
    protected $signature = 'database:snapshot
                            {path : Destination SQL file path}
                            {--force : Overwrite an existing snapshot file}';

    protected $description = 'Create a production-safe MySQL snapshot using mysqldump before deployment migrations';

    public function handle(): int
    {
        $connection = DB::connection();
        $config = $connection->getConfig();

        if (($config['driver'] ?? null) !== 'mysql') {
            $this->error('Database snapshots currently support MySQL connections only.');

            return self::FAILURE;
        }

        $database = trim((string) ($config['database'] ?? ''));
        $username = (string) ($config['username'] ?? '');

        if ($database === '' || $username === '') {
            $this->error('Database name/username are missing from the active MySQL connection.');

            return self::FAILURE;
        }

        $path = (string) $this->argument('path');

        if (! str_starts_with($path, DIRECTORY_SEPARATOR)) {
            $path = base_path($path);
        }

        $directory = dirname($path);
        File::ensureDirectoryExists($directory);

        if (File::exists($path) && ! $this->option('force')) {
            $this->error('Snapshot already exists: '.$path);

            return self::FAILURE;
        }

        if (File::exists($path)) {
            File::delete($path);
        }

        $binary = trim((string) config('deploy.database_backup.binary', 'mysqldump'));

        if ($binary === '') {
            $this->error('mysqldump binary is not configured.');

            return self::FAILURE;
        }

        $command = [
            $binary,
            '--single-transaction',
            '--quick',
            '--skip-lock-tables',
            '--no-tablespaces',
            '--default-character-set=utf8mb4',
            '--user='.$username,
            '--result-file='.$path,
        ];

        $socket = trim((string) ($config['unix_socket'] ?? ''));

        if ($socket !== '') {
            $command[] = '--socket='.$socket;
        } else {
            $command[] = '--host='.(string) ($config['host'] ?? '127.0.0.1');
            $command[] = '--port='.(string) ($config['port'] ?? '3306');
            $command[] = '--protocol=TCP';
        }

        $command[] = $database;

        $environment = [];

        if (($config['password'] ?? '') !== '') {
            // Keep the password out of the process command line.
            $environment['MYSQL_PWD'] = (string) $config['password'];
        }

        $process = new Process(
            $command,
            base_path(),
            $environment,
            null,
            max(30, (int) config('deploy.database_backup.timeout_seconds', 300))
        );

        $process->run();

        if (! $process->isSuccessful()) {
            @unlink($path);
            $this->error('Database snapshot failed.');
            $this->line(trim($process->getErrorOutput()));

            return self::FAILURE;
        }

        if (! File::exists($path) || File::size($path) <= 0) {
            @unlink($path);
            $this->error('mysqldump completed without producing a usable snapshot.');

            return self::FAILURE;
        }

        @chmod($path, 0600);

        $this->info('Database snapshot created successfully.');
        $this->line('Path: '.$path);
        $this->line('Size: '.number_format((int) File::size($path)).' bytes');
        $this->line('SHA-256: '.hash_file('sha256', $path));

        return self::SUCCESS;
    }
}
