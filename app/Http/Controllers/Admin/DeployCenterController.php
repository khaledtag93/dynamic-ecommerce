<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Commerce\AdminActivityLogService;
use App\Services\System\DeployCenterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DeployCenterController extends Controller
{
    public function __construct(
        protected AdminActivityLogService $adminActivityLogService,
    ) {
    }

    public function index(Request $request, DeployCenterService $deployCenterService): View
    {
        $data = $deployCenterService->dashboard($request->string('log')->toString());

        return view('admin.settings.deploy-center', $data);
    }

    public function deploy(Request $request, DeployCenterService $deployCenterService): RedirectResponse
    {
        $validated = $request->validate([
            'confirm_phrase' => ['required', 'string', 'in:DEPLOY'],
            'action_mode' => ['nullable', 'string', Rule::in(['execute', 'dry_run'])],
        ]);

        $actionMode = (string) ($validated['action_mode'] ?? 'execute');
        $result = $deployCenterService->deploy([
            'action_mode' => $actionMode,
        ]);

        $this->logAction('deploy', $result, [
            'action_mode' => $actionMode,
            'dry_run' => $actionMode === 'dry_run',
            'confirm_phrase' => 'DEPLOY',
        ]);

        return redirect()
            ->route('admin.settings.deploy-center', array_filter([
                'log' => $result['latest_log_name'] ?? null,
            ]))
            ->with($result['ok'] ? 'success' : 'error', $result['message'])
            ->with('deploy_center_result', $result);
    }

    public function rollback(Request $request, DeployCenterService $deployCenterService): RedirectResponse
    {
        $validated = $request->validate([
            'backup_name' => ['required', 'string'],
            'confirm_phrase' => ['required', 'string', 'in:ROLLBACK'],
            'action_mode' => ['nullable', 'string', Rule::in(['execute', 'dry_run'])],
        ]);

        $actionMode = (string) ($validated['action_mode'] ?? 'execute');
        $result = $deployCenterService->rollback($validated['backup_name'], [
            'action_mode' => $actionMode,
        ]);

        $this->logAction('rollback', $result, [
            'backup_name' => $validated['backup_name'],
            'action_mode' => $actionMode,
            'dry_run' => $actionMode === 'dry_run',
            'confirm_phrase' => 'ROLLBACK',
        ]);

        return redirect()
            ->route('admin.settings.deploy-center', array_filter([
                'log' => $result['latest_log_name'] ?? null,
            ]))
            ->with($result['ok'] ? 'success' : 'error', $result['message'])
            ->with('deploy_center_result', $result);
    }

    protected function logAction(string $action, array $result, array $meta = []): void
    {
        $this->adminActivityLogService->log(
            'deploy_center',
            $action,
            $result['ok'] ?? false
                ? __('Deploy Center :action completed successfully.', ['action' => $action])
                : __('Deploy Center :action finished with an issue.', ['action' => $action]),
            Auth::id(),
            null,
            array_merge($meta, [
                'status' => $result['status'] ?? null,
                'ok' => (bool) ($result['ok'] ?? false),
                'exit_code' => $result['exit_code'] ?? null,
                'ran_on' => $result['ran_on'] ?? null,
                'latest_log_name' => $result['latest_log_name'] ?? null,
                'latest_commit' => $result['git']['short_hash'] ?? null,
                'git_branch' => $result['git']['branch'] ?? null,
                'readiness_status' => $result['readiness']['overall'] ?? null,
                'readiness_blockers' => $result['readiness']['counts']['blockers'] ?? null,
                'readiness_warnings' => $result['readiness']['counts']['warnings'] ?? null,
                'duration_ms' => $result['duration_ms'] ?? null,
                'started_at' => $result['started_at'] ?? null,
                'completed_at' => $result['completed_at'] ?? null,
            ]),
        );
    }
}
