<?php

namespace App\Services\System;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Safe, bounded failure stage for an authorized internal diagnostic.
 *
 * HTTP behavior is unchanged (404/503 with generic messages). Only privileged
 * PHP callers may opt to read ->stage; no original exception or payload is
 * chained, logged or returned in HTTP responses.
 */
final class SafeRuntimeEvidenceFailure extends HttpException
{
    public const IDENTITY_GATE = 'IDENTITY_GATE';
    public const REQUIRED_SCHEMA = 'REQUIRED_SCHEMA';
    public const SETTINGS_READ = 'SETTINGS_READ';
    public const AUTOMATION_COUNT = 'AUTOMATION_COUNT';
    public const FAILED_EMAIL_AGGREGATES = 'FAILED_EMAIL_AGGREGATES';

    public readonly string $stage;

    public function __construct(string $stage, int $statusCode = 503)
    {
        $this->stage = in_array($stage, [
            self::IDENTITY_GATE,
            self::REQUIRED_SCHEMA,
            self::SETTINGS_READ,
            self::AUTOMATION_COUNT,
            self::FAILED_EMAIL_AGGREGATES,
        ], true) ? $stage : 'UNKNOWN';

        parent::__construct(
            $statusCode,
            $statusCode === 404 ? 'Not Found' : 'Safe diagnostic snapshot unavailable.'
        );
    }
}
