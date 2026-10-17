<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\System\SafeRuntimeEvidenceService;
use Illuminate\Http\JsonResponse;

/**
 * Deliberately independent of NotificationCenterController::buildViewData()
 * and its ensureDefaults() DB-writing page initialization.
 */
final class SafeRuntimeEvidenceController extends Controller
{
    public function __invoke(SafeRuntimeEvidenceService $evidence): JsonResponse
    {
        return response()->json($evidence->snapshot())
            ->header('Cache-Control', 'no-store, private')
            ->header('X-Content-Type-Options', 'nosniff');
    }
}
