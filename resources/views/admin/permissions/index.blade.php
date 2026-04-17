@extends('layouts.admin')

@section('title', __('Permissions') . ' | Admin')

@section('content')
<div class="admin-page-shell">
<div class="admin-page-header">
    <div>
        <div class="admin-kicker">{{ __('Access Control') }}</div>
        <h1 class="admin-page-title">{{ __('Permissions & Staff Roles') }}</h1>
        <p class="admin-page-description">{{ __('Role assignments are now enforced across the admin routes, sidebar navigation, and key back-office screens.') }}</p>
    </div>
</div>

<div class="row g-4 mb-4">
    @foreach($roles as $role)
        <div class="col-xl-3 col-md-6">
            <div class="admin-card h-100"><div class="admin-card-body">
                <div class="admin-inline-label">{{ __('Role') }}</div>
                <div class="fw-bold fs-5 mb-2">{{ $role->name }}</div>
                <p class="text-muted small mb-3">{{ $role->description }}</p>
                <div class="text-muted small mb-3">{{ trans_choice(':count permission|:count permissions', $role->permissions->count(), ['count' => $role->permissions->count()]) }}</div>
                <div class="d-flex flex-wrap gap-2">
                    @foreach($role->permissions->take(5) as $permission)
                        <span class="badge rounded-pill text-bg-light border">{{ $permission->name }}</span>
                    @endforeach
                    @if($role->permissions->count() > 5)
                        <span class="badge rounded-pill text-bg-warning-subtle border">+{{ $role->permissions->count() - 5 }}</span>
                    @endif
                </div>
            </div></div>
        </div>
    @endforeach
</div>


<div class="row g-4 mb-4">
    <div class="col-xl-6">
        <div class="admin-card"><div class="admin-card-body">
            <h4 class="mb-3">{{ __('Create custom staff role') }}</h4>
            <form method="POST" action="{{ route('admin.permissions.roles.store') }}">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label fw-semibold">{{ __('Role name') }}</label><input type="text" name="name" class="form-control" required></div>
                    <div class="col-md-6"><label class="form-label fw-semibold">{{ __('Description') }}</label><input type="text" name="description" class="form-control"></div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">{{ __('Allowed capabilities') }}</label>
                        <div class="row g-2">
                            @foreach($assignablePermissions as $permission)
                                <div class="col-md-6"><label class="form-check border rounded-3 p-2 d-block"><input class="form-check-input me-2" type="checkbox" name="permission_slugs[]" value="{{ $permission->slug }}"><span class="fw-semibold">{{ $permission->name }}</span><div class="small text-muted">{{ $permission->description }}</div></label></div>
                            @endforeach
                        </div>
                    </div>
                    <div class="col-12"><div class="admin-form-actions-compact"><button type="submit" class="btn btn-primary">{{ __('Create role') }}</button></div></div>
                </div>
            </form>
        </div></div>
    </div>
    <div class="col-xl-6">
        <div class="admin-card"><div class="admin-card-body">
            <div class="admin-section-heading">
                <div>
                    <h4 class="admin-section-title">{{ __('Create custom permission label') }}</h4>
                    <p class="admin-section-subtitle">{{ __('Use this for future custom modules or visibility rules in addition to the built-in route permissions.') }}</p>
                </div>
            </div>
            <form method="POST" action="{{ route('admin.permissions.custom.store') }}">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label fw-semibold">{{ __('Permission name') }}</label><input type="text" name="name" class="form-control" required></div>
                    <div class="col-md-6"><label class="form-label fw-semibold">{{ __('Group') }}</label><input type="text" name="group" class="form-control" placeholder="custom"></div>
                    <div class="col-12"><label class="form-label fw-semibold">{{ __('Description') }}</label><textarea name="description" rows="3" class="form-control"></textarea></div>
                    <div class="col-12"><div class="admin-form-actions-compact"><button type="submit" class="btn btn-outline-primary">{{ __('Create permission') }}</button></div></div>
                </div>
            </form>
        </div></div>
    </div>
</div>

<div class="admin-card mb-4"><div class="admin-card-body">
    <div class="admin-table-toolbar">
        <div>
            <h4 class="mb-1">{{ __('Custom roles') }}</h4>
            <div class="text-muted small">{{ __('Review editable roles, their permissions, and custom access labels in one place.') }}</div>
        </div>
        <span class="badge rounded-pill text-bg-light border">{{ $roles->where('is_system', false)->count() }}</span>
    </div>
    <div class="d-flex flex-column gap-3">
        @forelse($roles->where('is_system', false) as $role)
            <div class="border rounded-4 p-3">
                <form method="POST" action="{{ route('admin.permissions.roles.update', $role) }}">
                    @csrf
                    @method('PATCH')
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label fw-semibold">{{ __('Role name') }}</label><input type="text" name="name" value="{{ $role->name }}" class="form-control" required></div>
                        <div class="col-md-8"><label class="form-label fw-semibold">{{ __('Description') }}</label><input type="text" name="description" value="{{ $role->description }}" class="form-control"></div>
                        <div class="col-12"><label class="form-label fw-semibold">{{ __('Allowed capabilities') }}</label><div class="row g-2">
                            @foreach($assignablePermissions as $permission)
                                <div class="col-md-6"><label class="form-check border rounded-3 p-2 d-block"><input class="form-check-input me-2" type="checkbox" name="permission_slugs[]" value="{{ $permission->slug }}" @checked($role->permissions->contains('slug', $permission->slug))><span class="fw-semibold">{{ $permission->name }}</span><div class="small text-muted">{{ $permission->description }}</div></label></div>
                            @endforeach
                        </div></div>
                        <div class="col-12 d-flex justify-content-between gap-2 flex-wrap">
                            <div class="admin-form-actions-compact"><button type="submit" class="btn btn-primary">{{ __('Save role') }}</button></div>
                        </div>
                    </div>
                </form>
                <form method="POST" action="{{ route('admin.permissions.roles.destroy', $role) }}" class="mt-3" onsubmit="return confirm('{{ __('Delete this custom role?') }}')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger btn-sm">{{ __('Delete role') }}</button>
                </form>
            </div>
        @empty
            <div class="admin-empty-state py-4"><div class="empty-icon"><i class="mdi mdi-shield-outline"></i></div><h5 class="mb-2">{{ __('No custom roles yet') }}</h5></div>
        @endforelse
    </div>
</div></div>

<div class="row g-4">
    <div class="col-xl-5">
        <div class="admin-card"><div class="admin-card-body">
            <div class="d-flex justify-content-between align-items-center gap-3 mb-3 flex-wrap">
                <h4 class="mb-0">{{ __('Admin users') }}</h4>
                <span class="badge rounded-pill text-bg-light border">{{ trans_choice(':count account|:count accounts', $admins->count(), ['count' => $admins->count()]) }}</span>
            </div>
            <div class="d-flex flex-column gap-3">
                @forelse($admins as $admin)
                    @php
                        $currentRole = $admin->roles->first();
                        $resolvedPermissions = collect($admin->resolved_permissions ?? []);
                    @endphp
                    <div class="p-3 rounded-4 border">
                        <div class="d-flex justify-content-between gap-3 flex-wrap align-items-start">
                            <div>
                                <div class="fw-bold">{{ $admin->name }}</div>
                                <div class="text-muted small">{{ $admin->email }}</div>
                                <div class="text-muted small mt-1">{{ __('Current role') }}: <strong>{{ $admin->primaryRoleName() }}</strong></div>
                                <div class="text-muted small">{{ __('Effective access') }}: {{ $resolvedPermissions->count() ?: __('Full legacy fallback') }}</div>
                            </div>
                            <form method="POST" action="{{ route('admin.permissions.users.role', $admin) }}" class="d-flex gap-2 align-items-center flex-wrap">
                                @csrf
                                @method('PATCH')
                                <select name="role_id" class="form-select form-select-sm" style="min-width: 220px;">
                                    <option value="">{{ __('Super Admin (legacy fallback)') }}</option>
                                    @foreach($roles as $role)
                                        <option value="{{ $role->id }}" @selected(optional($admin->roles->first())->id === $role->id)>{{ $role->name }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="btn btn-sm btn-primary">{{ __('Save') }}</button>
                            </form>
                        </div>

                        <div class="mt-3 d-flex flex-wrap gap-2">
                            @forelse($currentRole?->permissions ?? [] as $permission)
                                <span class="badge rounded-pill text-bg-light border">{{ $permission->name }}</span>
                            @empty
                                <span class="badge rounded-pill text-bg-dark">{{ __('All permissions') }}</span>
                            @endforelse
                        </div>
                    </div>
                @empty
                    <div class="admin-empty-state py-4"><div class="empty-icon"><i class="mdi mdi-account-key-outline"></i></div><h5 class="mb-2">{{ __('No admin users found') }}</h5></div>
                @endforelse
            </div>
        </div></div>
    </div>
    <div class="col-xl-7">
        <div class="admin-card"><div class="admin-card-body">
            <h4 class="mb-3">{{ __('Permission matrix') }}</h4>
            <div class="accordion" id="permissionGroups">
                @foreach($permissionGroups as $group => $permissions)
                    <div class="accordion-item border rounded-4 mb-3 overflow-hidden">
                        <h2 class="accordion-header" id="heading-{{ $group }}">
                            <button class="accordion-button {{ $loop->first ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-{{ $group }}">
                                {{ \Illuminate\Support\Str::headline($group) }}
                            </button>
                        </h2>
                        <div id="collapse-{{ $group }}" class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}" data-bs-parent="#permissionGroups">
                            <div class="accordion-body p-0">
                                <div class="table-responsive">
                                    <table class="table admin-table mb-0 align-middle">
                                        <thead>
                                            <tr>
                                                <th>{{ __('Permission') }}</th>
                                                <th>{{ __('Description') }}</th>
                                                <th>{{ __('Available in roles') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($permissions as $permission)
                                                <tr>
                                                    <td class="fw-semibold">{{ $permission->name }}</td>
                                                    <td class="text-muted small">{{ $permission->description }}</td>
                                                    <td>
                                                        <div class="d-flex flex-wrap gap-2">
                                                            @foreach($roles->filter(fn($role) => $role->permissions->contains('slug', $permission->slug)) as $role)
                                                                <span class="badge rounded-pill text-bg-light border">{{ $role->name }}</span>
                                                            @endforeach
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div></div>
    </div>
</div>
</div>
@endsection
