<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\AuthorizationService;
use App\Services\Commerce\CustomerAccountStatementService;
use App\Services\Commerce\AdminActivityLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function __construct(
        protected AuthorizationService $authorizationService,
        protected CustomerAccountStatementService $statementService,
        protected AdminActivityLogService $adminActivityLogService,
    ) {
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->string('search'));
        $role = (string) $request->string('role');
        $activity = (string) $request->string('activity');
        $value = (string) $request->string('value');
        $perPage = max(12, min(100, (int) $request->integer('per_page', 12)));

        $users = User::query()
            ->with('roles:id,name')
            ->withCount('orders')
            ->withSum('orders', 'grand_total')
            ->when($search, function ($query, $search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($role !== '', fn ($query) => $query->where('role_as', (int) $role))
            ->when($activity === 'buyers', fn ($query) => $query->has('orders'))
            ->when($activity === 'no_orders', fn ($query) => $query->doesntHave('orders'))
            ->when($value === 'repeat', fn ($query) => $query->has('orders', '>=', 2))
            ->when($value === 'high_value', fn ($query) => $query->whereHas('orders')->withSum('orders as value_spend', 'grand_total')->orderByDesc('value_spend'))
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        $queueStats = [
            'buyers' => User::has('orders')->count(),
            'no_orders' => User::doesntHave('orders')->count(),
            'repeat_buyers' => User::has('orders', '>=', 2)->count(),
        ];

        if ($request->header('X-Live-List') === '1') {
            return response()->view('admin.customers._results', compact(
                'users',
                'search',
                'role',
                'activity',
                'value',
                'perPage',
                'queueStats',
            ));
        }

        $stats = $queueStats + [
            'total' => User::count(),
            'admins' => User::where('role_as', 1)->count(),
            'customers' => User::where('role_as', 0)->count(),
            'revenue' => (float) Order::whereNotNull('user_id')->sum('grand_total'),
        ];

        return view('admin.customers.index', compact('users', 'search', 'role', 'activity', 'value', 'perPage', 'stats', 'queueStats'));
    }

    public function show(User $user)
    {
        $user->load(['roles', 'orders' => function ($query) {
            $query->latest('id')->take(10);
        }]);

        $staffRoles = collect();
        if (request()->user()?->isSuperAdmin()) {
            $this->authorizationService->syncDefaults();
            $staffRoles = Role::query()->where('slug', '!=', 'super_admin')->orderBy('name')->get();
        }

        $summary = [
            'orders_count' => $user->orders()->count(),
            'total_spend' => (float) $user->orders()->sum('grand_total'),
            'refund_total' => (float) $user->orders()->sum('refund_total'),
            'latest_order_at' => optional($user->orders()->latest('id')->first())->created_at,
            'net_spend' => max(0, (float) $user->orders()->sum('grand_total') - (float) $user->orders()->sum('refund_total')),
            'average_order_value' => $user->orders()->count() > 0 ? (float) $user->orders()->avg('grand_total') : 0,
        ];

        return view('admin.customers.show', compact('user', 'summary', 'staffRoles'));
    }

    public function statement(Request $request, User $user)
    {
        $filters = $this->statementFilters($request);
        $statement = $this->statementService->build($user, $filters);

        return view('admin.customers.statement', compact('user', 'filters', 'statement'));
    }

    public function statementPrint(Request $request, User $user)
    {
        $filters = $this->statementFilters($request);
        $statement = $this->statementService->build($user, $filters);

        return view('admin.customers.statement-print', compact('user', 'filters', 'statement'));
    }

    public function statementExport(Request $request, User $user): StreamedResponse
    {
        $filters = $this->statementFilters($request);
        $statement = $this->statementService->build($user, $filters);
        $fileName = 'customer-statement-'.$user->id.'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($statement) {
            $handle = fopen('php://output', 'w');

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                __('Date'),
                __('Type'),
                __('Reference'),
                __('Status'),
                __('Amount'),
                __('Currency'),
                __('Details'),
            ]);

            foreach ($statement['movements'] as $movement) {
                fputcsv($handle, [
                    $movement['occurred_at']->format('Y-m-d H:i:s'),
                    $movement['type_label'],
                    $movement['reference'],
                    $movement['status_label'],
                    $movement['amount'] === null ? '' : number_format((float) $movement['amount'], 2, '.', ''),
                    $movement['currency'] ?? '',
                    $movement['details'],
                ]);
            }

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function statementFilters(Request $request): array
    {
        $validated = $request->validate([
            'type' => ['nullable', Rule::in(['order', 'payment', 'refund', 'return'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        return [
            'type' => (string) ($validated['type'] ?? ''),
            'date_from' => $validated['date_from'] ?? now()->subYear()->toDateString(),
            'date_to' => $validated['date_to'] ?? now()->toDateString(),
        ];
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $validated = $request->validate([
            'role_as' => ['required', 'in:0,1'],
            'role_id' => [
                'required_if:role_as,1',
                'nullable',
                'integer',
                Rule::exists('roles', 'id')->where(fn ($query) => $query->where('slug', '!=', 'super_admin')),
            ],
        ]);

        DB::transaction(function () use ($user, $validated, $request) {
            $account = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            // Owner changes require a separate, audited transfer path.
            abort_if($account->isSuperAdmin() || $account->id === $request->user()->id, 403);

            $oldRoleAs = (int) $account->role_as;
            $oldRoleId = $account->roles()->value('roles.id');

            if ((int) $validated['role_as'] === 1) {
                $account->roles()->sync([(int) $validated['role_id']]);
                $account->update(['role_as' => 1]);
            } else {
                $account->update(['role_as' => 0]);
                $account->roles()->detach();
            }

            $this->adminActivityLogService->log(
                'account_access',
                'customer_access_updated',
                __('Account access updated for :customer.', ['customer' => $account->email]),
                $request->user()->id,
                $account,
                [
                    'old_role_as' => $oldRoleAs,
                    'new_role_as' => (int) $validated['role_as'],
                    'old_role_id' => $oldRoleId,
                    'new_role_id' => (int) $validated['role_as'] === 1 ? (int) $validated['role_id'] : null,
                ]
            );
        });

        return back()->with('success', __('Account access updated successfully.'));
    }
}
