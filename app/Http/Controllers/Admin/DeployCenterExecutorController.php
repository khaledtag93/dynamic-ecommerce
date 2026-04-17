<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\System\DeployExecutorService;
use App\Services\System\DeployRemoteRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeployCenterExecutorController extends Controller
{
    public function status(Request $request, DeployExecutorService $deployExecutorService, DeployRemoteRequest $deployRemoteRequest): JsonResponse
    {
        if (! $deployRemoteRequest->isValid($request)) {
            return response()->json([
                'ok' => false,
                'status' => 'unauthorized',
                'message' => __('Invalid deploy executor signature.'),
            ], 403);
        }

        return response()->json([
            'ok' => true,
            'status' => 'ready',
            'message' => __('Remote deploy executor is available.'),
            'data' => $deployExecutorService->status($request->string('selected_log')->toString()),
        ]);
    }

    public function execute(Request $request, DeployExecutorService $deployExecutorService, DeployRemoteRequest $deployRemoteRequest): JsonResponse
    {
        if (! $deployRemoteRequest->isValid($request)) {
            return response()->json([
                'ok' => false,
                'status' => 'unauthorized',
                'message' => __('Invalid deploy executor signature.'),
            ], 403);
        }

        $validated = $request->validate([
            'action' => ['required', 'string', 'in:deploy,rollback'],
            'backup_name' => ['nullable', 'string'],
            'action_mode' => ['nullable', 'string', 'in:execute,dry_run'],
        ]);

        $options = [
            'action_mode' => (string) ($validated['action_mode'] ?? 'execute'),
        ];

        $result = $validated['action'] === 'rollback'
            ? $deployExecutorService->rollback((string) ($validated['backup_name'] ?? ''), $options)
            : $deployExecutorService->deploy($options);

        return response()->json($result, ($result['ok'] ?? false) ? 200 : 422);
    }
}
