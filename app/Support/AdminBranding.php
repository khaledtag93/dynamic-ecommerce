<?php

namespace App\Support;

class AdminBranding
{
    protected static function normalizePath(?string $path): ?string
    {
        return MediaPath::normalizeRelative($path);
    }

    protected static function existingUrlForCandidate(?string $candidate): ?string
    {
        if (blank($candidate)) {
            return null;
        }

        if (MediaPath::isExternal((string) $candidate)) {
            return (string) $candidate;
        }

        $candidate = self::normalizePath($candidate);

        if (blank($candidate)) {
            return null;
        }

        $basename = basename($candidate);

        $candidates = array_values(array_filter(array_unique([
            $candidate,
            'branding/' . $basename,
            'assets/img/' . $basename,
            'admin/images/' . $basename,
            $basename,
        ])));

        foreach ($candidates as $item) {
            if (file_exists(MediaPath::uploadsRootPath($item))) {
                return asset('uploads/' . $item);
            }

            if (file_exists(MediaPath::publicRootPath($item))) {
                return asset($item);
            }
        }

        return null;
    }

    public static function fallbackCandidates(string $type = 'generic'): array
    {
        return match ($type) {
            'logo' => [
                'branding/logo.png',
                'branding/logo.jpg',
                'assets/img/logo.png',
                'assets/img/logo2.png',
                'assets/img/logo3.png',
                'assets/img/logo4.png',
                'assets/img/logo5.png',
                'admin/images/logo.svg',
            ],
            'favicon' => [
                'branding/favicon.ico',
                'branding/favicon.png',
                'branding/favicon.svg',
                'favicon.ico',
            ],
            'admin_logo' => [
                'branding/admin-logo.png',
                'branding/admin_logo.png',
                'admin/images/logo.svg',
                'assets/img/logo.png',
                'assets/img/logo2.png',
            ],
            'hero_banner' => [
                'branding/hero-banner.jpg',
                'branding/hero-banner.png',
                'branding/banner.jpg',
                'branding/banner.png',
            ],
            'promo_banner' => [
                'branding/promo-banner-1.jpg',
                'branding/promo-banner-1.png',
                'branding/promo-banner-2.jpg',
                'branding/promo-banner-2.png',
                'branding/promo-banner-3.jpg',
                'branding/promo-banner-3.png',
            ],
            default => [],
        };
    }

    public static function resolveMediaPath(?string $path, string $type = 'generic'): ?string
    {
        if (filled($path) && self::existingUrlForCandidate($path)) {
            return self::normalizePath($path);
        }

        foreach (self::fallbackCandidates($type) as $fallback) {
            if (self::existingUrlForCandidate($fallback)) {
                return $fallback;
            }
        }

        return filled($path) ? self::normalizePath($path) : null;
    }

    public static function mediaUrl(?string $path, string $type = 'generic'): ?string
    {
        $resolved = self::resolveMediaPath($path, $type);

        if (blank($resolved)) {
            return null;
        }

        return self::existingUrlForCandidate($resolved);
    }
}
