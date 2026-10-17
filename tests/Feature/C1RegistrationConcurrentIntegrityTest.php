<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class C1RegistrationConcurrentIntegrityTest extends TestCase
{
    /**
     * Force two independent HTTP kernels past Laravel's unique-email validator
     * and release them simultaneously at Eloquent's "creating" event.
     * Separate PHP processes have separate DB connections and cookie jars.
     *
     * The test is deliberately NOT wrapped in RefreshDatabase transactions:
     * those would hide fixtures from the independent processes.
     */
    public function test_same_email_registration_collision_is_safe_and_creates_only_one_account(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertSame('mysql', DB::getDefaultConnection());
        $this->assertSame('dynamic_test', DB::connection()->getDatabaseName());

        $index = DB::select("SHOW INDEX FROM users WHERE Column_name = 'email' AND Non_unique = 0");
        $this->assertNotEmpty($index, 'The DB must enforce unique email independently of request validation.');

        $email = 'ci-race-'.Str::lower(Str::random(18)).'@example.test';
        $directory = storage_path('framework/testing/register-race-'.Str::random(16));
        $this->assertTrue(mkdir($directory, 0700, true));
        $processes = [];

        try {
            foreach (['A', 'B'] as $device) {
                $process = new Process([
                    PHP_BINARY,
                    base_path('tests/Support/C1RegistrationRaceWorker.php'),
                    $email,
                    $directory,
                    $device,
                ], base_path(), [
                    'APP_ENV' => 'testing',
                    'DB_CONNECTION' => 'mysql',
                    'DB_DATABASE' => 'dynamic_test',
                    'MAIL_MAILER' => 'array',
                    'QUEUE_CONNECTION' => 'sync',
                ]);
                $process->setTimeout(35);
                $process->start();
                $processes[$device] = $process;
            }

            $limit = microtime(true) + 25;
            while (!(is_file("$directory/A.ready") && is_file("$directory/B.ready"))) {
                if (microtime(true) > $limit || !$processes['A']->isRunning() || !$processes['B']->isRunning()) {
                    $this->fail(
                        'Both independent registration requests must reach the pre-INSERT barrier. '
                        .$this->safeWorkerDiagnostics($processes)
                    );
                }
                usleep(20000);
            }

            // The UNIQUE validator has already passed for both processes.
            file_put_contents("$directory/release", 'go');

            $results = [];
            foreach ($processes as $device => $process) {
                $process->wait();
                $this->assertTrue(
                    $process->isSuccessful(),
                    "$device worker failed to return HTTP summary: ".$this->safeWorkerDiagnostics([$device => $process])
                );
                $result = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
                $results[$device] = $result;
                $this->assertTrue($result['passedValidationBeforeInsert'], "Worker $device never reached the DB race.");
                $this->assertSame(200, $result['registrationFormStatus']);
            }

            $this->assertNotSame(
                $results['A']['guestSessionId'],
                $results['B']['guestSessionId'],
                'The requests must come from independent guest sessions.'
            );

            // Important integrity guarantee: database uniqueness wins the race.
            $this->assertSame(1, User::query()->where('email', $email)->count());

            $statuses = [$results['A']['postStatus'], $results['B']['postStatus']];
            sort($statuses);
            $this->assertSame(
                [302, 302],
                $statuses,
                'A losing concurrent signup must fail validation gracefully, not surface HTTP 500. '
                .json_encode($results, JSON_THROW_ON_ERROR)
            );

            $losers = array_values(array_filter(
                $results,
                fn (array $result): bool => in_array('email', $result['errorKeys'], true)
            ));
            $this->assertCount(1, $losers, 'Exactly one losing request must show normal email validation feedback.');
            foreach ($results as $result) {
                $this->assertFalse($result['leakedSqlDetail'], 'Raw SQL error must never reach an HTTP response.');
            }
        } finally {
            if (is_dir($directory)) {
                file_put_contents("$directory/release", 'cleanup');
            }
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop(1);
                }
            }
            // Test-owned, synthetic rows only, never other accounts.
            User::query()->where('email', $email)->delete();
            foreach (['A.ready', 'B.ready', 'release'] as $file) {
                @unlink("$directory/$file");
            }
            @rmdir($directory);
        }
    }

    private function safeWorkerDiagnostics(array $processes): string
    {
        return implode('; ', array_map(
            static fn (string $device, Process $process) => "$device: exit=".($process->getExitCode() ?? 'running')
                .',stdout='.substr(trim($process->getOutput()), 0, 250),
            array_keys($processes),
            array_values($processes)
        ));
    }
}
