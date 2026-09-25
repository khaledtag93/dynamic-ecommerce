<?php

namespace Tests\Feature;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class WorkforceBladeIntegrityTest extends TestCase
{
    public function test_all_blades_do_not_contain_corrupted_php_class_namespaces(): void
    {
        $root = resource_path('views');
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

        $invalid = [];

        foreach ($iterator as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());

            if (str_contains($contents, 'AppModels') || str_contains($contents, 'IlluminateSupport')) {
                $invalid[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname());
            }
        }

        $this->assertSame([], $invalid, 'Corrupted Blade PHP class namespaces found in: '.implode(', ', $invalid));
    }
}
