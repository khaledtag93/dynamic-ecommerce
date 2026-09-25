<?php

namespace Tests\Feature;

use Tests\TestCase;

class SharedShellTranslationIntegrityTest extends TestCase
{
    public function test_shared_shell_translation_keys_exist_in_english_and_arabic(): void
    {
        $paths = [
            resource_path('views/layouts/inc/admin/navbar.blade.php'),
            resource_path('views/layouts/inc/admin/sidebar.blade.php'),
            resource_path('views/layouts/inc/language-switcher.blade.php'),
            resource_path('views/components/admin/page-help.blade.php'),
            resource_path('views/components/admin/section-tabs.blade.php'),
            resource_path('views/admin/dashboard.blade.php'),
            resource_path('views/admin/orders/index.blade.php'),
            resource_path('views/admin/orders/_results.blade.php'),
            resource_path('views/admin/orders/show.blade.php'),
            resource_path('views/admin/customers/index.blade.php'),
            resource_path('views/admin/customers/_results.blade.php'),
            resource_path('views/admin/customers/show.blade.php'),
            resource_path('views/admin/permissions/index.blade.php'),
        ];

        $keys = [];

        foreach ($paths as $path) {
            $source = file_get_contents($path);
            preg_match_all('/__\\(\\s*[\'"]([^\'"]+)[\'"]\\s*\\)/', $source, $matches);

            foreach ($matches[1] as $key) {
                $keys[$key] = true;
            }
        }

        $english = json_decode(file_get_contents(lang_path('en.json')), true, 512, JSON_THROW_ON_ERROR);
        $arabic = json_decode(file_get_contents(lang_path('ar.json')), true, 512, JSON_THROW_ON_ERROR);

        foreach (array_keys($keys) as $key) {
            $this->assertArrayHasKey($key, $english, "Missing English shared-shell translation: {$key}");
            $this->assertArrayHasKey($key, $arabic, "Missing Arabic shared-shell translation: {$key}");
            $this->assertNotSame('', trim((string) $english[$key]), "Empty English shared-shell translation: {$key}");
            $this->assertNotSame('', trim((string) $arabic[$key]), "Empty Arabic shared-shell translation: {$key}");
        }
    }
}
