<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SafeStorefrontLink implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || trim((string) $value) === '') {
            return;
        }

        $link = trim((string) $value);

        if (str_starts_with($link, '#') || str_starts_with($link, '/') || str_starts_with($link, '?')) {
            return;
        }

        $scheme = strtolower((string) parse_url($link, PHP_URL_SCHEME));

        if (in_array($scheme, ['http', 'https', 'mailto', 'tel'], true)) {
            return;
        }

        $fail(__('The link must use a safe web, email, phone, relative, or anchor destination.'));
    }
}
