<?php

namespace App\Services\System;

use Illuminate\Http\Request;

class DeployRemoteRequest
{
    public function headers(string $body = '', ?int $timestamp = null): array
    {
        $timestamp = $timestamp ?: now()->timestamp;

        return [
            'X-Deploy-Timestamp' => (string) $timestamp,
            'X-Deploy-Signature' => $this->sign($timestamp, $body),
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }

    public function isValid(Request $request): bool
    {
        $secret = $this->secret();
        $timestamp = (int) $request->header('X-Deploy-Timestamp');
        $signature = (string) $request->header('X-Deploy-Signature', '');

        if ($secret === '' || $timestamp <= 0 || $signature === '') {
            return false;
        }

        $maxSkew = (int) config('deploy.remote.max_request_age_seconds', 300);

        if (abs(now()->timestamp - $timestamp) > $maxSkew) {
            return false;
        }

        return hash_equals($this->sign($timestamp, $request->getContent()), $signature);
    }

    public function secret(): string
    {
        return (string) config('deploy.remote.shared_secret', '');
    }

    protected function sign(int $timestamp, string $body): string
    {
        return hash_hmac('sha256', $timestamp."\n".$body, $this->secret());
    }
}
