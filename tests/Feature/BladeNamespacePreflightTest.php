<?php

namespace Tests\Feature;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class BladeNamespacePreflightTest extends TestCase
{
    public function test_admin_blades_do_not_contain_corrupted_namespace_patterns(): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(resource_path('views/admin'))
        );

        $offenders = [];

        foreach ($iterator as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $source = file_get_contents($file->getPathname());

            if (preg_match('/(AppModels|IlluminateSupport)[A-Za-z0-9_:]*/', $source)) {
                $offenders[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname());
            }
        }

        $this->assertSame([], $offenders, 'Corrupted Blade namespace references found: '.implode(', ', $offenders));
    }
}
