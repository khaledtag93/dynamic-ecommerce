<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductImage;
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


    public function test_product_main_image_url_does_not_expose_missing_local_media(): void
    {
        $root = sys_get_temp_dir() . '/dynamic-media-' . uniqid('', true);
        $productDirectory = $root . '/uploads/products';

        mkdir($productDirectory, 0755, true);

        try {
            config([
                'store.public_root_path' => $root,
                'app.url' => 'https://v42.tag-marketplace.com',
            ]);

            $product = new Product();
            $image = new ProductImage(['image_path' => 'products/example.jpg', 'is_main' => true]);

            $product->setRelation('mainImage', $image);
            $product->setRelation('productImages', collect([$image]));

            $this->assertNull($product->main_image_url);

            file_put_contents($productDirectory . '/example.jpg', 'image-bytes');

            $this->assertStringEndsWith('/uploads/products/example.jpg', $product->main_image_url);
        } finally {
            @unlink($productDirectory . '/example.jpg');
            @rmdir($productDirectory);
            @rmdir($root . '/uploads');
            @rmdir($root);
        }
    }
}
