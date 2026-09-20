<?php

namespace App\Services\System;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class DeployRemoteRequest
{
    public function headers(string $body = '', ?int $timestamp = null, ?string $requestId = null): array
    {
        $timestamp = $timestamp ?: now()->timestamp;
        $requestId = $requestId ?: (string) Str::uuid();

        return [
            'X-Deploy-Timestamp' => (string) $timestamp,
            'X-Deploy-Request-Id' => $requestId,
            'X-Deploy-Signature' => $this->sign($timestamp, $requestId, $body),
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }

    public function isValid(Request $request): bool
    {
        $secret = $this->secret();
        $timestamp = (int) $request->header('X-Deploy-Timestamp');
        $requestId = trim((string) $request->header('X-Deploy-Request-Id', ''));
        $signature = (string) $request->header('X-Deploy-Signature', '');

        if (
            $secret === ''
            || $timestamp <= 0
            || $requestId === ''
            || strlen($requestId) > 128
            || ! preg_match('/^[A-Za-z0-9._:-]+$/', $requestId)
            || $signature === ''
        ) {
            return false;
        }

        $maxSkew = max(30, (int) config('deploy.remote.max_request_age_seconds', 300));

        if (abs(now()->timestamp - $timestamp) > $maxSkew) {
            return false;
        }

        if (! hash_equals($this->sign($timestamp, $requestId, $request->getContent()), $signature)) {
            return false;
        }

        try {
            return Cache::add(
                'deploy-remote-request:'.hash('sha256', $requestId),
                true,
                now()->addSeconds($maxSkew * 2)
            );
        } catch (\Throwable $exception) {
            // Deploy authentication must fail closed if replay protection
            // cannot persist its one-time request marker.
            return false;
        }
    }

    public function secret(): string
    {
        return (string) config('deploy.remote.shared_secret', '');
    }

    protected function sign(int $timestamp, string $requestId, string $body): string
    {
        return hash_hmac('sha256', $timestamp."\n".$requestId."\n".$body, $this->secret());
    }
}
