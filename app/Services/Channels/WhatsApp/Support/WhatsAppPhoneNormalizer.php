<?php

namespace App\Services\Channels\WhatsApp\Support;

class WhatsAppPhoneNormalizer
{
    public function normalize(?string $phone, string $defaultCountryCode = '20'): ?string
    {
        if (blank($phone)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $phone);

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (str_starts_with($digits, '0')) {
            $digits = $defaultCountryCode.ltrim($digits, '0');
        }

        return $digits;
    }
}