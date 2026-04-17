<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\AuthorizationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PermissionController extends Controller
{
    public function __construct(protected AuthorizationService $authorizationService)
    {
    }

    public function index()
    {
        $this->authorizationService->syncDefaults();

        $roles = Role::query()->with('permissions')->orderByDesc('is_system')->orderBy('id')->get();
        $admins = User::query()
            ->where('role_as', 1)
            ->with(['roles.permissions'])
            ->latest('id')
            ->get()
            ->map(function (User $admin) {
                $role = $admin->roles->first();
                $admin->setAttribute('resolved_permissions', $role?->permissions?->pluck('slug')->values()->all() ?? []);

                return $admin;
            });

        $permissionGroups = $this->authorizationService->groupedPermissions();
        $assignablePermissions = Permission::query()->orderBy('group')->orderBy('name')->get();

        return view('admin.permissions.index', compact('roles', 'admins', 'permissionGroups', 'assignablePermissions'));
    }

    public function storeRole(Request $request): RedirectResponse
    {
        $this->authorizationService->syncDefaults();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'permission_slugs' => ['nullable', 'array'],
            'permission_slugs.*' => ['string', Rule::exists('permissions', 'slug')],
        ]);

        $role = Role::query()->create([
            'name' => $validated['name'],
            'slug' => 'custom_' . Str::slug($validated['name'], '_') . '_' . Str::lower(Str::random(4)),
            'description' => $validated['description'] ?? null,
            'is_system' => false,
        ]);

        $role->permissions()->sync(
            Permission::query()->whereIn('slug', $validated['permission_slugs'] ?? [])->pluck('id')->all()
        );

        return back()->with('success', __('Custom staff role created successfully.'));
    }

    public function updateRole(Request $request, Role $role): RedirectResponse
    {
        abort_if($role->is_system, 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'permission_slugs' => ['nullable', 'array'],
            'permission_slugs.*' => ['string', Rule::exists('permissions', 'slug')],
        ]);

        $role->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        $role->permissions()->sync(
            Permission::query()->whereIn('slug', $validated['permission_slugs'] ?? [])->pluck('id')->all()
        );

        return back()->with('success', __('Custom staff role updated successfully.'));
    }

    public function destroyRole(Role $role): RedirectResponse
    {
        abort_if($role->is_system, 403);

        $role->users()->detach();
        $role->permissions()->detach();
        $role->delete();

        return back()->with('success', __('Custom staff role deleted successfully.'));
    }

    public function storePermission(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'group' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $group = Str::slug($validated['group'] ?: 'custom', '_');
        $slug = 'custom.' . Str::slug($validated['name'], '_');

        Permission::query()->firstOrCreate(
            ['slug' => $slug],
            [
                'name' => $validated['name'],
                'group' => $group,
                'description' => $validated['description'] ?? 'Custom permission created from the admin dashboard.',
            ]
        );

        return back()->with('success', __('Custom permission created successfully.'));
    }

    public function updateUserRole(Request $request, User $user): RedirectResponse
    {
        $this->authorizationService->syncDefaults();

        $validated = $request->validate([
            'role_id' => ['nullable', Rule::exists('roles', 'id')],
        ]);

        if ((int) $user->role_as !== 1) {
            return back()->with('error', __('Only admin accounts can receive back-office staff roles.'));
        }

        $roleId = $validated['role_id'] ?? null;
        $user->roles()->sync($roleId ? [$roleId] : []);

        return back()->with('success', __('Staff role updated successfully.'));
    }
}
