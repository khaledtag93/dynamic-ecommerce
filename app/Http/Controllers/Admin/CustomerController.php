<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->string('search'));
        $role = (string) $request->string('role');

        $users = User::query()
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
        $user->load(['orders' => function ($query) {
            $query->latest('id')->take(10);
        }]);

        $summary = [
            'orders_count' => $user->orders()->count(),
            'total_spend' => (float) $user->orders()->sum('grand_total'),
            'refund_total' => (float) $user->orders()->sum('refund_total'),
            'latest_order_at' => optional($user->orders()->latest('id')->first())->created_at,
        ];

        return view('admin.customers.show', compact('user', 'summary'));
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'role_as' => ['required', 'in:0,1'],
        ]);

        if ((int) $validated['role_as'] === 0 && auth()->id() === $user->id) {
            return back()->with('error', 'You cannot remove admin access from your own account.');
        }

        $user->update([
            'role_as' => (int) $validated['role_as'],
        ]);

        return back()->with('success', $user->name . ' role updated successfully.');
    }
}
