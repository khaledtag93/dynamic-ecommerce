<?php

namespace App\Support;

class MediaPath
{
    public static function directory(string $key, ?string $fallback = null): string
    {
        return (string) config("store.media_directories.{$key}", $fallback ?? $key);
    }

    public static function publicRootPath(?string $path = null): string
    {
        $splitPublicRoot = dirname(base_path()) . DIRECTORY_SEPARATOR . 'public_html';
        $defaultPublicRoot = public_path();

        $root = is_dir($splitPublicRoot) ? $splitPublicRoot : $defaultPublicRoot;

        if (blank($path)) {
            return $root;
        }

        return $root . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) $path), DIRECTORY_SEPARATOR);
    }

    public static function uploadsRootPath(?string $path = null): string
    {
        return self::publicRootPath('uploads' . ($path ? '/' . ltrim($path, '/') : ''));
    }

    public static function uploadDirectoryPath(string $key): string
    {
        return self::uploadsRootPath(self::directory($key));
    }
    public static function normalizeRelative(?string $path, ?string $defaultDirectory = null): ?string
    {
        if (blank($path)) {
            return null;
        }

        $path = trim((string) $path);
        $path = str_replace('\\', '/', $path);
        $path = rawurldecode($path);

        if (self::isExternal($path)) {
            return $path;
        }

        $normalized = ltrim($path, '/');
        $normalized = preg_replace('#^(?:https?:)?//[^/]+/#', '', $normalized);
        $normalized = preg_replace('#^/?public/#', '', $normalized);
        $normalized = preg_replace('#^/?storage/#', '', $normalized);
        $normalized = preg_replace('#^/?uploads/#', '', $normalized);
        $normalized = ltrim((string) $normalized, '/');

        if ($normalized === '') {
            return null;
        }

        if ($defaultDirectory && ! str_contains($normalized, '/')) {
            $normalized = trim($defaultDirectory, '/') . '/' . $normalized;
        }

        return $normalized;
    }

    public static function assetUrl(?string $path, ?string $defaultDirectory = null): ?string
    {
        if (blank($path)) {
            return null;
        }

        if (self::isExternal((string) $path)) {
            return (string) $path;
        }

        $relative = self::normalizeRelative($path, $defaultDirectory);

        if (blank($relative)) {
            return null;
        }

        return asset('uploads/' . ltrim($relative, '/'));
    }

    public static function publicUploadPath(?string $path, ?string $defaultDirectory = null): ?string
    {
        $relative = self::normalizeRelative($path, $defaultDirectory);

        if (blank($relative) || self::isExternal((string) $relative)) {
            return null;
        }

        return self::uploadsRootPath(ltrim($relative, '/'));
    }

    public static function isExternal(string $path): bool
    {
        return str_starts_with($path, 'http://')
            || str_starts_with($path, 'https://')
            || str_starts_with($path, '//')
            || str_starts_with($path, 'data:');
    }
}
