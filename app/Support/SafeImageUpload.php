<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use RuntimeException;

class SafeImageUpload
{
    public static function extensionFor(UploadedFile $file): string
    {
        $path = $file->getRealPath();

        if (! is_string($path) || $path === '' || ! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('Uploaded image temporary file is missing or unreadable.');
        }

        $imageInfo = @getimagesize($path);
        $imageType = is_array($imageInfo) ? ($imageInfo[2] ?? null) : null;

        $allowedTypes = [
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_PNG => 'png',
        ];

        if (defined('IMAGETYPE_WEBP')) {
            $allowedTypes[IMAGETYPE_WEBP] = 'webp';
        }

        if (! is_int($imageType) || ! isset($allowedTypes[$imageType])) {
            throw new RuntimeException('Uploaded file is not a supported JPEG, PNG, or WebP image.');
        }

        return $allowedTypes[$imageType];
    }
}
