<?php

namespace Tests\Feature;

use App\Support\SafeImageUpload;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use Tests\TestCase;

class SafeImageUploadTest extends TestCase
{
    public function test_extension_is_derived_from_image_content_not_original_filename(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'dynamic-image-');
        $this->assertNotFalse($path);

        try {
            file_put_contents(
                $path,
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl2n5sAAAAASUVORK5CYII=')
            );

            $upload = new UploadedFile(
                $path,
                'payload.php',
                'application/x-php',
                null,
                true
            );

            $this->assertSame('png', SafeImageUpload::extensionFor($upload));
        } finally {
            if (is_string($path) && is_file($path)) {
                @unlink($path);
            }
        }
    }

    public function test_non_image_content_is_rejected_even_with_image_filename(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'dynamic-image-');
        $this->assertNotFalse($path);

        try {
            file_put_contents($path, '<?php echo "not an image";');

            $upload = new UploadedFile(
                $path,
                'fake.jpg',
                'image/jpeg',
                null,
                true
            );

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Uploaded file is not a supported JPEG, PNG, or WebP image.');

            SafeImageUpload::extensionFor($upload);
        } finally {
            if (is_string($path) && is_file($path)) {
                @unlink($path);
            }
        }
    }
}
