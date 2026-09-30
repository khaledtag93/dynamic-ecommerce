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
        $search = mb_substr(trim((string) $request->string('search')), 0, 100);
        $escapedSearch = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
        $like = '%' . $escapedSearch . '%';
        $role = (string) $request->string('role');
        $activity = (string) $request->string('activity');
        $value = (string) $request->string('value');
        $valueCurrency = strtoupper(mb_substr(trim((string) $request->string('value_currency')), 0, 3));
        $perPage = max(12, min(100, (int) $request->integer('per_page', 12)));
        $currencyExpression = "UPPER(COALESCE(NULLIF(currency, ''), 'EGP'))";

        $valueCurrencies = Order::query()
            ->commerciallyRealized()
            ->whereNotNull('user_id')
            ->selectRaw($currencyExpression.' as value_currency')
            ->distinct()
            ->orderBy('value_currency')
            ->pluck('value_currency')
            ->values();

        if ($valueCurrency !== '' && ! $valueCurrencies->contains($valueCurrency)) {
            $valueCurrency = '';
        }

        if ($value === 'high_value' && $valueCurrency === '' && $valueCurrencies->count() === 1) {
            $valueCurrency = (string) $valueCurrencies->first();
        }

        $rankingRequiresCurrency = $value === 'high_value' && $valueCurrency === '' && $valueCurrencies->count() > 1;

        $users = User::query()
            ->with('roles:id,name')
            ->withCount('orders')
            ->withCount(['orders as realized_orders_count' => fn ($query) => $query->commerciallyRealized()])
            ->when($search, function ($query) use ($like) {
                $query->where(function ($inner) use ($like) {
                    $inner->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like);
                });
            })
            ->when($role !== '', fn ($query) => $query->where('role_as', (int) $role))
            ->when($activity === 'buyers', fn ($query) => $query->whereHas('orders', fn ($orders) => $orders->commerciallyRealized()))
            ->when($activity === 'no_orders', fn ($query) => $query->doesntHave('orders'))
            ->when($value === 'repeat', fn ($query) => $query->whereHas('orders', fn ($orders) => $orders->commerciallyRealized(), '>=', 2))
            ->when($value === 'high_value' && $valueCurrency !== '', function ($query) use ($valueCurrency, $currencyExpression) {
                $query
                    ->whereHas('orders', fn ($orders) => $orders
                        ->commerciallyRealized()
                        ->whereRaw($currencyExpression.' = ?', [$valueCurrency]))
                    ->withSum(['orders as ranked_orders_sum_grand_total' => fn ($orders) => $orders
                        ->commerciallyRealized()
                        ->whereRaw($currencyExpression.' = ?', [$valueCurrency])], 'grand_total')
                    ->withSum(['orders as ranked_orders_sum_refund_total' => fn ($orders) => $orders
                        ->commerciallyRealized()
                        ->whereRaw($currencyExpression.' = ?', [$valueCurrency])], 'refund_total')
                    ->orderByRaw('(COALESCE(ranked_orders_sum_grand_total, 0) - COALESCE(ranked_orders_sum_refund_total, 0)) DESC');
            })
            ->when($value === 'high_value' && $valueCurrency === '', fn ($query) => $query
                ->whereHas('orders', fn ($orders) => $orders->commerciallyRealized()))
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        $pageUserIds = $users->getCollection()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $spendRowsByUser = $pageUserIds === []
            ? collect()
            : Order::query()
                ->commerciallyRealized()
                ->whereIntegerInRaw('user_id', $pageUserIds)
                ->selectRaw('user_id, '.$currencyExpression.' as statement_currency')
                ->selectRaw('SUM(grand_total) as gross_total, SUM(refund_total) as refund_total')
                ->groupBy('user_id')
                ->groupByRaw($currencyExpression)
                ->orderBy('statement_currency')
                ->get()
                ->groupBy('user_id');

        $users->setCollection($users->getCollection()->map(function (User $user) use ($spendRowsByUser): User {
            $rows = collect($spendRowsByUser->get($user->id, collect()))
                ->map(fn ($row): array => [
                    'currency' => (string) $row->statement_currency,
                    'amount' => round(max(0, (float) $row->gross_total - (float) $row->refund_total), 2),
                ])
                ->values()
                ->all();

            $user->setAttribute('realized_spend_by_currency', $rows);

            return $user;
        }));

        $queueStats = [
            'buyers' => User::whereHas('orders', fn ($orders) => $orders->commerciallyRealized())->count(),
            'no_orders' => User::doesntHave('orders')->count(),
            'repeat_buyers' => User::whereHas('orders', fn ($orders) => $orders->commerciallyRealized(), '>=', 2)->count(),
        ];

        if ($request->header('X-Live-List') === '1') {
            return response()->view('admin.customers._results', compact(
                'users',
                'search',
                'role',
                'activity',
                'value',
                'valueCurrency',
                'valueCurrencies',
                'rankingRequiresCurrency',
                'perPage',
                'queueStats',
            ));
        }

        $stats = $queueStats + [
            'total' => User::count(),
            'admins' => User::where('role_as', 1)->count(),
            'customers' => User::where('role_as', 0)->count(),
            'revenue_by_currency' => Order::query()
                ->commerciallyRealized()
                ->whereNotNull('user_id')
                ->selectRaw($currencyExpression.' as currency')
                ->selectRaw('SUM(grand_total - refund_total) as statement_total')
                ->groupByRaw($currencyExpression)
                ->orderBy('currency')
                ->get()
                ->map(fn ($row): array => [
                    'currency' => (string) $row->currency,
                    'amount' => round((float) $row->statement_total, 2),
                ])
                ->values(),
        ];

        return view('admin.customers.index', compact('users', 'search', 'role', 'activity', 'value', 'valueCurrency', 'valueCurrencies', 'rankingRequiresCurrency', 'perPage', 'stats', 'queueStats'));
    }

    public function show(User $user)
    {
        $user->load(['roles', 'orders' => function ($query) {
            $query->orderByRaw('COALESCE(placed_at, created_at) DESC')
                ->orderByDesc('id')
                ->take(10);
        }]);

        $staffRoles = collect();
        if (request()->user()?->isSuperAdmin()) {
            $this->authorizationService->syncDefaults();
            $staffRoles = Role::query()->where('slug', '!=', 'super_admin')->orderBy('name')->get();
        }

        $realizedOrders = $user->orders()->commerciallyRealized();
        $currencyExpression = "UPPER(COALESCE(NULLIF(currency, ''), 'EGP'))";
        $spendByCurrency = (clone $realizedOrders)
            ->selectRaw($currencyExpression.' as currency')
            ->selectRaw('COUNT(*) as orders_count, SUM(grand_total) as gross_total, SUM(refund_total) as refund_total')
            ->groupByRaw($currencyExpression)
            ->orderBy('currency')
            ->get()
            ->map(function ($row): array {
                $gross = round((float) $row->gross_total, 2);
                $refunds = round((float) $row->refund_total, 2);
                $net = round(max(0, $gross - $refunds), 2);
                $count = (int) $row->orders_count;

                return [
                    'currency' => (string) $row->currency,
                    'orders_count' => $count,
                    'gross_total' => $gross,
                    'refund_total' => $refunds,
                    'net_total' => $net,
                    'average_order_value' => $count > 0 ? round($net / $count, 2) : 0.0,
                ];
            })
            ->values();

        $summary = [
            'orders_count' => $user->orders()->count(),
            'realized_orders_count' => (int) $spendByCurrency->sum('orders_count'),
            'spend_by_currency' => $spendByCurrency,
            'latest_order_at' => optional(
                (clone $realizedOrders)
                    ->orderByRaw('COALESCE(placed_at, created_at) DESC')
                    ->orderByDesc('id')
                    ->first()
            )?->placed_at ?: optional(
                (clone $realizedOrders)
                    ->orderByRaw('COALESCE(placed_at, created_at) DESC')
                    ->orderByDesc('id')
                    ->first()
            )?->created_at,
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
        $statement = $this->statementService->buildBounded(
            $user,
            $filters,
            CustomerAccountStatementService::PRINT_LIMIT,
        );

        return view('admin.customers.statement-print', compact('user', 'filters', 'statement'));
    }

    public function statementExport(Request $request, User $user): StreamedResponse
    {
        $filters = $this->statementFilters($request);
        $statement = $this->statementService->buildBounded(
            $user,
            $filters,
            CustomerAccountStatementService::EXPORT_LIMIT,
        );
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
                    $this->csvSafeText($movement['type_label']),
                    $this->csvSafeText($movement['reference']),
                    $this->csvSafeText($movement['status_label']),
                    $movement['amount'] === null ? '' : number_format((float) $movement['amount'], 2, '.', ''),
                    $this->csvSafeText($movement['currency'] ?? ''),
                    $this->csvSafeText($movement['details']),
                ]);
            }

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Statement-Row-Limit' => (string) CustomerAccountStatementService::EXPORT_LIMIT,
            'X-Statement-Matching-Rows' => (string) $statement['matching_count'],
            'X-Statement-Truncated' => $statement['truncated'] ? '1' : '0',
        ]);
    }

    private function csvSafeText(mixed $value): string
    {
        $text = (string) $value;

        return preg_match('/^\s*[=+\-@]/u', $text) === 1
            ? "'".$text
            : $text;
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
