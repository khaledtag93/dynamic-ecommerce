<?php

namespace Tests\Feature;

use App\Support\MediaPath;
use Tests\TestCase;

class MediaPublicRootIsolationTest extends TestCase
{
    public function test_explicit_public_root_override_is_used_for_media_paths(): void
    {
        config(['store.public_root_path' => '/tmp/dynamic-qas-public']);

        $this->assertSame(
            '/tmp/dynamic-qas-public',
            MediaPath::publicRootPath()
        );

        $this->assertSame(
            '/tmp/dynamic-qas-public/uploads/category',
            str_replace('\\', '/', MediaPath::uploadsRootPath('category'))
        );
    }

    public function test_media_asset_url_stays_domain_relative(): void
    {
        config(['app.url' => 'https://v42.tag-marketplace.com']);

        $url = MediaPath::assetUrl('category/example.webp');

        $this->assertStringEndsWith('/uploads/category/example.webp', $url);
    }
}
