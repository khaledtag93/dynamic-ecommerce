<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * Offline-only QAS regression. Exercises the exact Bash guard excerpt against
 * disposable local Git fixtures, NEVER sources/runs deploy-qas.sh.
 */
final class QasDeployPublicStorageTreeGuardTest extends TestCase
{
    private string $sandbox;
    private string $baseline;
    private Filesystem $files;

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem();
        $this->sandbox = sys_get_temp_dir().'/flowra-qas-git-tree-'.bin2hex(random_bytes(8));
        self::assertTrue($this->files->makeDirectory($this->sandbox, 0700, true));
        $this->git('init', '-q');
        $this->git('config', 'user.email', 'flowra-source-test@example.invalid');
        $this->git('config', 'user.name', 'Flowra CI Fixture');
        self::assertTrue($this->files->makeDirectory($this->sandbox.'/public/storage/products', 0700, true));
        file_put_contents($this->sandbox.'/public/storage/products/image.jpg', 'fixture-image-v1');
        file_put_contents($this->sandbox.'/README.txt', 'baseline');
        $this->commitAll('baseline');
        $this->baseline = $this->git('rev-parse', 'HEAD');
    }

    protected function tearDown(): void
    {
        self::assertTrue($this->files->deleteDirectory($this->sandbox));
        parent::tearDown();
    }

    public function test_same_public_storage_tree_is_accepted_when_unrelated_files_change(): void
    {
        file_put_contents($this->sandbox.'/README.txt', 'release-only change');
        $target = $this->commitAll('unrelated release change');

        $result = $this->executeGuard($this->baseline, $target);
        self::assertSame(0, $result->getExitCode(), $result->getOutput().$result->getErrorOutput());
    }

    public function test_modified_protected_product_is_rejected(): void
    {
        file_put_contents($this->sandbox.'/public/storage/products/image.jpg', 'fixture-image-v2');
        $target = $this->commitAll('modified tracked image');

        $result = $this->executeGuard($this->baseline, $target);
        self::assertSame(92, $result->getExitCode());
        self::assertStringContainsString('BLOCKED:', $result->getOutput());
    }

    public function test_added_protected_product_is_rejected(): void
    {
        file_put_contents($this->sandbox.'/public/storage/products/other.jpg', 'another tracked product');
        $target = $this->commitAll('added protected image');

        self::assertSame(92, $this->executeGuard($this->baseline, $target)->getExitCode());
    }

    public function test_deleted_protected_tree_is_rejected(): void
    {
        $this->git('rm', '-r', '-q', 'public/storage');
        $target = $this->commitAll('removed protected directory');

        self::assertSame(92, $this->executeGuard($this->baseline, $target)->getExitCode());
    }

    public function test_replacing_tree_with_a_blob_is_rejected(): void
    {
        $this->git('rm', '-r', '-q', 'public/storage');
        // Depending on Git version, tracked-file deletion may also remove
        // either empty parent directory; only remove directories still present.
        foreach (['/public/storage/products', '/public/storage'] as $relative) {
            $path = $this->sandbox.$relative;
            if (is_dir($path)) {
                self::assertTrue(rmdir($path));
            }
        }
        self::assertFalse(is_dir($this->sandbox.'/public/storage'));
        // Git can also remove the now-empty parent public/ directory.
        // Recreate only this disposable fixture parent, not the protected tree.
        if (!is_dir($this->sandbox.'/public')) {
            self::assertTrue(mkdir($this->sandbox.'/public', 0700, true));
        }
        self::assertSame(strlen('not a directory'), file_put_contents($this->sandbox.'/public/storage', 'not a directory'));
        $target = $this->commitAll('replaced protected tree with blob');

        self::assertSame(92, $this->executeGuard($this->baseline, $target)->getExitCode());
    }

    public function test_invalid_missing_or_wrong_baseline_fails_closed(): void
    {
        file_put_contents($this->sandbox.'/README.txt', 'unrelated');
        $target = $this->commitAll('unrelated');

        self::assertSame(92, $this->executeGuard('0000000000000000000000000000000000000000', $target)->getExitCode());
        self::assertSame(92, $this->executeGuard($this->baseline, '0000000000000000000000000000000000000000')->getExitCode());
    }

    public function test_existing_non_storage_protected_paths_still_block_on_any_target_collision(): void
    {
        $source = $this->deploySource();
        self::assertStringContainsString(
            'for protected_path in scripts/qas-queue-cron.sh scripts/qas-scheduler-cron.sh storage/app public/uploads; do',
            $source
        );
        self::assertStringContainsString('git ls-tree -r --name-only "$TARGET_SHA" -- "$protected_path"', $source);
        self::assertStringContainsString('BLOCKED: target Git tree collides with QAS runtime data', $source);
        self::assertStringContainsString('git reset --hard "$TARGET_SHA"', $source);
        self::assertStringNotContainsString('git reset --hard "origin/$BRANCH"', $source);
        self::assertStringContainsString('public storage points outside QAS', $source);
    }

    private function executeGuard(string $oldSha, string $newSha): Process
    {
        $source = $this->deploySource();
        $begin = '# BEGIN_QAS_PUBLIC_STORAGE_TREE_GUARD';
        $end = '# END_QAS_PUBLIC_STORAGE_TREE_GUARD';
        $start = strpos($source, $begin);
        $stop = strpos($source, $end);

        self::assertNotFalse($start, 'Missing Bash tree guard begin marker');
        self::assertNotFalse($stop, 'Missing Bash tree guard end marker');
        self::assertGreaterThan($start, $stop);

        $guard = substr($source, $start, $stop + strlen($end) - $start);
        self::assertStringContainsString('git rev-parse --verify', $guard);
        self::assertStringContainsString('git cat-file -t', $guard);
        self::assertStringContainsString('"$BASELINE_PUBLIC_STORAGE_TREE" = "$TARGET_PUBLIC_STORAGE_TREE"', $guard);

        $script = 'set -Eeuo pipefail'.PHP_EOL
            .'fail() { printf "%s\\n" "$*" ; exit 92; }'.PHP_EOL
            .'PREVIOUS_COMMIT='.escapeshellarg($oldSha).PHP_EOL
            .'TARGET_SHA='.escapeshellarg($newSha).PHP_EOL
            .$guard.PHP_EOL;

        $process = new Process(['bash', '-c', $script], $this->sandbox);
        $process->run();

        return $process;
    }

    private function deploySource(): string
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/deploy-qas.sh');
        self::assertIsString($source);

        return $source;
    }

    private function commitAll(string $subject): string
    {
        $this->git('add', '-A');
        $this->git('commit', '-q', '-m', $subject);

        return $this->git('rev-parse', 'HEAD');
    }

    private function git(string ...$args): string
    {
        $process = new Process(array_merge(['git'], $args), $this->sandbox);
        $process->run();
        self::assertSame(0, $process->getExitCode(), $process->getOutput().$process->getErrorOutput());

        return trim($process->getOutput());
    }
}
