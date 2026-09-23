<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\AuthorizationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function __construct(protected AuthorizationService $authorizationService)
    {
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->string('search'));
        $role = (string) $request->string('role');

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
            ->latest('id')
            ->paginate(12)
            ->withQueryString();

        $stats = [
            'total' => User::count(),
            'admins' => User::where('role_as', 1)->count(),
            'customers' => User::where('role_as', 0)->count(),
            'buyers' => User::has('orders')->count(),
        ];

        return view('admin.customers.index', compact('users', 'search', 'role', 'stats'));
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
        ];

        return view('admin.customers.show', compact('user', 'summary', 'staffRoles'));
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

            if ((int) $validated['role_as'] === 1) {
                $account->roles()->sync([(int) $validated['role_id']]);
                $account->update(['role_as' => 1]);
            } else {
                $account->update(['role_as' => 0]);
                $account->roles()->detach();
            }
        });

        return back()->with('success', __('Account access updated successfully.'));
    }
}
