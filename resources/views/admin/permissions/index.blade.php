@extends('layouts.admin')

@section('title', __('Permissions & Staff Roles') . ' | Admin')

@php
    $systemRoles = $roles->where('is_system', true);
    $customRoles = $roles->where('is_system', false);
    $assignedAdmins = $admins->filter(fn ($admin) => $admin->roles->isNotEmpty());
    $unassignedAdmins = $admins->filter(fn ($admin) => $admin->roles->isEmpty());
    $permissionCount = $assignablePermissions->count();
    $groupCount = $permissionGroups->count();
@endphp

@section('content')
<x-admin.page-header
    :kicker="__('Access control')"
    :title="__('Permissions & Staff Roles')"
    :description="__('Manage staff access through explicit roles, focused assignments, and a searchable permission matrix.')"
/>

<div class="admin-page-shell" data-permissions-workspace data-admin-section-tabs="permissions">
    <div class="row g-3">
        @foreach([
            ['label' => __('Staff accounts'), 'value' => $admins->count(), 'copy' => __('Admin accounts currently registered for back-office access.'), 'icon' => 'mdi-account-key-outline'],
            ['label' => __('System roles'), 'value' => $systemRoles->count(), 'copy' => __('Protected built-in roles maintained by the authorization service.'), 'icon' => 'mdi-shield-lock-outline'],
            ['label' => __('Custom roles'), 'value' => $customRoles->count(), 'copy' => __('Editable roles created for your operating model.'), 'icon' => 'mdi-shield-edit-outline'],
            ['label' => __('Permissions'), 'value' => $permissionCount, 'copy' => __('Capabilities grouped across :count access areas.', ['count' => $groupCount]), 'icon' => 'mdi-key-chain-variant'],
        ] as $card)
            <div class="col-sm-6 col-xl-3">
                <x-admin.stat-card
                    :label="$card['label']"
                    :value="$card['value']"
                    :icon="$card['icon']"
                    :help="$card['copy']"
                    class="h-100"
                />
            </div>
        @endforeach
    </div>

    @if($unassignedAdmins->isNotEmpty())
        <div class="alert alert-warning rounded-4 border-0 mb-0">
            <div class="d-flex gap-3 align-items-start">
                <i class="mdi mdi-shield-alert-outline fs-4"></i>
                <div>
                    <div class="fw-bold">{{ __('Unassigned admin accounts require attention') }}</div>
                    <div class="small">{{ trans_choice(':count admin account has no explicit staff role and cannot enter the back office.|:count admin accounts have no explicit staff role and cannot enter the back office.', $unassignedAdmins->count(), ['count' => $unassignedAdmins->count()]) }}</div>
                </div>
            </div>
        </div>
    @endif

    <x-admin.section-tabs id="permissions" :sections="[
        'overview' => __('Overview'),
        'staff' => __('Staff assignments'),
        'roles' => __('Roles'),
        'matrix' => __('Permission matrix'),
    ]" />

    <section class="admin-card" id="permissions-panel-overview" role="tabpanel" aria-labelledby="permissions-tab-overview" data-admin-section-panel="overview">
        <div class="admin-card-body">
            <div class="admin-section-heading mb-4">
                <div>
                    <h4 class="admin-section-title">{{ __('Access model at a glance') }}</h4>
                    <p class="admin-section-subtitle">{{ __('Every back-office account now needs an explicit staff role. Missing role data never grants Super Admin access.') }}</p>
                </div>
                <span class="admin-chip"><i class="mdi mdi-shield-check-outline me-1"></i>{{ __('Explicit roles enforced') }}</span>
            </div>

            <div class="row g-3">
                @foreach($systemRoles as $role)
                    <div class="col-md-6 col-xl-4">
                        <article class="border rounded-4 p-3 h-100">
                            <div class="d-flex justify-content-between align-items-start gap-3 mb-2">
                                <div>
                                    <div class="fw-bold fs-5">{{ __($role->name) }}</div>
                                    <div class="text-muted small">{{ __($role->description) }}</div>
                                </div>
                                <span class="badge rounded-pill text-bg-light border">{{ __('System') }}</span>
                            </div>
                            <div class="small text-muted mb-3">{{ trans_choice(':count permission|:count permissions', $role->permissions->count(), ['count' => $role->permissions->count()]) }}</div>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach($role->permissions->take(4) as $permission)
                                    <span class="badge rounded-pill text-bg-light border">{{ __($permission->name) }}</span>
                                @endforeach
                                @if($role->permissions->count() > 4)
                                    <span class="badge rounded-pill text-bg-primary-subtle border">+{{ $role->permissions->count() - 4 }}</span>
                                @endif
                            </div>
                        </article>
                    </div>
                @endforeach
            </div>

            <div class="border rounded-4 p-3 mt-4 bg-light-subtle">
                <div class="d-flex gap-3 align-items-start">
                    <i class="mdi mdi-information-outline fs-4 text-primary"></i>
                    <div>
                        <div class="fw-bold">{{ __('How access works') }}</div>
                        <div class="text-muted small">{{ __('The admin flag identifies a back-office account. The assigned role determines its capabilities. Super Admin is full access only when the Super Admin role is explicitly assigned.') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="admin-card" id="permissions-panel-staff" role="tabpanel" aria-labelledby="permissions-tab-staff" data-admin-section-panel="staff">
        <div class="admin-card-body">
            <div class="admin-section-heading mb-4">
                <div>
                    <h4 class="admin-section-title">{{ __('Staff assignments') }}</h4>
                    <p class="admin-section-subtitle">{{ __('Assign one clear staff role to each admin account. Changes take effect through the existing server-side authorization checks.') }}</p>
                </div>
                <span class="badge rounded-pill text-bg-light border">{{ trans_choice(':count account|:count accounts', $admins->count(), ['count' => $admins->count()]) }}</span>
            </div>

            <div class="d-flex flex-column gap-3">
                @forelse($admins as $admin)
                    @php
                        $currentRole = $admin->roles->first();
                        $resolvedPermissions = collect($admin->resolved_permissions ?? []);
                    @endphp
                    <article class="border rounded-4 p-3">
                        <div class="row g-3 align-items-center">
                            <div class="col-lg-5">
                                <div class="d-flex align-items-center gap-3">
                                    <span class="admin-stat-icon flex-shrink-0"><i class="mdi mdi-account-outline"></i></span>
                                    <div class="min-w-0">
                                        <div class="fw-bold">{{ $admin->name }}</div>
                                        <div class="text-muted small text-break">{{ $admin->email }}</div>
                                        <div class="mt-2">
                                            @if($currentRole)
                                                <span class="badge rounded-pill text-bg-light border">{{ __($currentRole->name) }}</span>
                                                <span class="text-muted small ms-2">{{ trans_choice(':count permission|:count permissions', $resolvedPermissions->count(), ['count' => $resolvedPermissions->count()]) }}</span>
                                            @else
                                                <span class="badge rounded-pill text-bg-warning">{{ __('Unassigned admin') }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-7">
                                <form method="POST" action="{{ route('admin.permissions.users.role', $admin) }}" class="d-flex gap-2 align-items-end flex-wrap" data-submit-loading>
                                    @csrf
                                    @method('PATCH')
                                    <div class="flex-grow-1">
                                        <label class="form-label fw-semibold">{{ __('Staff role') }}</label>
                                        <select name="role_id" class="form-select" required>
                                            <option value="" disabled @selected(!$currentRole)>{{ __('Choose a role') }}</option>
                                            @foreach($roles as $role)
                                                <option value="{{ $role->id }}" @selected(optional($currentRole)->id === $role->id)>{{ __($role->name) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-primary btn-text-icon" data-loading-text="{{ __('Saving...') }}">
                                        <i class="mdi mdi-content-save-outline"></i><span>{{ __('Save assignment') }}</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="admin-empty-state py-4">
                        <div class="empty-icon"><i class="mdi mdi-account-key-outline"></i></div>
                        <h5 class="mb-2">{{ __('No admin users found') }}</h5>
                        <p class="text-muted mb-0">{{ __('Admin accounts will appear here after back-office access is granted.') }}</p>
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    <section id="permissions-panel-roles" role="tabpanel" aria-labelledby="permissions-tab-roles" data-admin-section-panel="roles">
        <div class="row g-4">
            <div class="col-xl-5">
                <div class="admin-card h-100">
                    <div class="admin-card-body">
                        <div class="admin-section-heading mb-4">
                            <div>
                                <h4 class="admin-section-title">{{ __('Create custom staff role') }}</h4>
                                <p class="admin-section-subtitle">{{ __('Start with a clear job responsibility, then select only the capabilities it needs.') }}</p>
                            </div>
                        </div>

                        <form method="POST" action="{{ route('admin.permissions.roles.store') }}" data-submit-loading>
                            @csrf
                            <div class="mb-3">
                                <label class="form-label fw-semibold">{{ __('Role name') }}</label>
                                <input type="text" name="name" class="form-control" maxlength="120" required>
                            </div>
                            <div class="mb-4">
                                <label class="form-label fw-semibold">{{ __('Description') }}</label>
                                <textarea name="description" rows="3" maxlength="1000" class="form-control"></textarea>
                            </div>

                            <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-3">
                                <label class="form-label fw-semibold mb-0">{{ __('Allowed capabilities') }}</label>
                                <span class="text-muted small">{{ __('Select only what this role needs.') }}</span>
                            </div>

                            <div class="permission-choice-list">
                                @foreach($permissionGroups as $group => $permissions)
                                    <details class="border rounded-4 mb-2" @if($loop->first) open @endif>
                                        <summary class="p-3 fw-semibold d-flex justify-content-between align-items-center gap-3">
                                            <span>{{ __(\Illuminate\Support\Str::headline($group)) }}</span>
                                            <span class="badge rounded-pill text-bg-light border">{{ $permissions->count() }}</span>
                                        </summary>
                                        <div class="px-3 pb-3 d-grid gap-2">
                                            @foreach($permissions as $permission)
                                                <label class="form-check border rounded-3 p-3 d-block mb-0">
                                                    <input class="form-check-input me-2" type="checkbox" name="permission_slugs[]" value="{{ $permission->slug }}">
                                                    <span class="fw-semibold">{{ __($permission->name) }}</span>
                                                    <div class="small text-muted mt-1">{{ __($permission->description) }}</div>
                                                </label>
                                            @endforeach
                                        </div>
                                    </details>
                                @endforeach
                            </div>

                            <button type="submit" class="btn btn-primary w-100 mt-3" data-loading-text="{{ __('Creating...') }}">{{ __('Create role') }}</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-xl-7">
                <div class="admin-card mb-4">
                    <div class="admin-card-body">
                        <div class="admin-section-heading mb-4">
                            <div>
                                <h4 class="admin-section-title">{{ __('Custom roles') }}</h4>
                                <p class="admin-section-subtitle">{{ __('Expand a role only when you need to edit its name, description, or capabilities.') }}</p>
                            </div>
                            <span class="badge rounded-pill text-bg-light border">{{ $customRoles->count() }}</span>
                        </div>

                        <div class="d-flex flex-column gap-3">
                            @forelse($customRoles as $role)
                                <details class="border rounded-4 overflow-hidden">
                                    <summary class="p-3 d-flex justify-content-between align-items-center gap-3">
                                        <div>
                                            <div class="fw-bold">{{ $role->name }}</div>
                                            <div class="text-muted small">{{ trans_choice(':count permission|:count permissions', $role->permissions->count(), ['count' => $role->permissions->count()]) }}</div>
                                        </div>
                                        <i class="mdi mdi-chevron-down"></i>
                                    </summary>
                                    <div class="border-top p-3">
                                        <form method="POST" action="{{ route('admin.permissions.roles.update', $role) }}" data-submit-loading>
                                            @csrf
                                            @method('PATCH')
                                            <div class="row g-3">
                                                <div class="col-md-5">
                                                    <label class="form-label fw-semibold">{{ __('Role name') }}</label>
                                                    <input type="text" name="name" value="{{ $role->name }}" class="form-control" maxlength="120" required>
                                                </div>
                                                <div class="col-md-7">
                                                    <label class="form-label fw-semibold">{{ __('Description') }}</label>
                                                    <input type="text" name="description" value="{{ $role->description }}" maxlength="1000" class="form-control">
                                                </div>
                                            </div>

                                            <div class="mt-4 d-grid gap-2">
                                                @foreach($permissionGroups as $group => $permissions)
                                                    <details class="border rounded-3">
                                                        <summary class="p-2 px-3 fw-semibold">{{ __(\Illuminate\Support\Str::headline($group)) }}</summary>
                                                        <div class="p-3 pt-1 row g-2">
                                                            @foreach($permissions as $permission)
                                                                <div class="col-md-6">
                                                                    <label class="form-check border rounded-3 p-2 d-block h-100">
                                                                        <input class="form-check-input me-2" type="checkbox" name="permission_slugs[]" value="{{ $permission->slug }}" @checked($role->permissions->contains('slug', $permission->slug))>
                                                                        <span class="fw-semibold">{{ __($permission->name) }}</span>
                                                                    </label>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    </details>
                                                @endforeach
                                            </div>

                                            <button type="submit" class="btn btn-primary mt-3" data-loading-text="{{ __('Saving...') }}">{{ __('Save role') }}</button>
                                        </form>

                                        <form method="POST" action="{{ route('admin.permissions.roles.destroy', $role) }}" class="mt-3"
                                              data-confirm-title="{{ __('Delete role') }}"
                                              data-confirm-message="{{ __('Delete this custom role?') }}"
                                              data-confirm-subtitle="{{ __('Assigned roles cannot be deleted until every staff account is reassigned.') }}"
                                              data-confirm-ok="{{ __('Delete role') }}"
                                              data-confirm-cancel="{{ __('Keep role') }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger btn-sm">{{ __('Delete role') }}</button>
                                        </form>
                                    </div>
                                </details>
                            @empty
                                <div class="admin-empty-state py-4">
                                    <div class="empty-icon"><i class="mdi mdi-shield-outline"></i></div>
                                    <h5 class="mb-2">{{ __('No custom roles yet') }}</h5>
                                    <p class="text-muted mb-0">{{ __('Create one only when the built-in roles do not match a real staff responsibility.') }}</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div class="admin-card">
                    <div class="admin-card-body">
                        <div class="admin-section-heading mb-4">
                            <div>
                                <h4 class="admin-section-title">{{ __('Create custom permission label') }}</h4>
                                <p class="admin-section-subtitle">{{ __('Reserve custom permissions for real modules or visibility rules that are enforced in code.') }}</p>
                            </div>
                        </div>
                        <form method="POST" action="{{ route('admin.permissions.custom.store') }}" class="row g-3" data-submit-loading>
                            @csrf
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">{{ __('Permission name') }}</label>
                                <input type="text" name="name" maxlength="120" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">{{ __('Group') }}</label>
                                <input type="text" name="group" maxlength="50" class="form-control" placeholder="{{ __('Custom') }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">{{ __('Description') }}</label>
                                <textarea name="description" rows="3" maxlength="1000" class="form-control"></textarea>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-outline-primary" data-loading-text="{{ __('Creating...') }}">{{ __('Create permission') }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="admin-card" id="permissions-panel-matrix" role="tabpanel" aria-labelledby="permissions-tab-matrix" data-admin-section-panel="matrix">
        <div class="admin-card-body">
            <div class="admin-section-heading mb-4">
                <div>
                    <h4 class="admin-section-title">{{ __('Permission matrix') }}</h4>
                    <p class="admin-section-subtitle">{{ __('Search by capability, description, group, or role instead of scanning the full matrix.') }}</p>
                </div>
                <span class="badge rounded-pill text-bg-light border">{{ trans_choice(':count permission|:count permissions', $permissionCount, ['count' => $permissionCount]) }}</span>
            </div>

            <div class="row g-3 align-items-end mb-4">
                <div class="col-lg-8">
                    <label class="form-label fw-semibold" for="permissionMatrixSearch">{{ __('Search permissions') }}</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="mdi mdi-magnify"></i></span>
                        <input id="permissionMatrixSearch" type="search" class="form-control" placeholder="{{ __('Permission, description, group, or role') }}" data-permission-search autocomplete="off">
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="border rounded-4 p-3 h-100">
                        <div class="small text-muted">{{ __('Visible results') }}</div>
                        <div class="fw-bold fs-4" data-permission-visible-count>{{ $permissionCount }}</div>
                    </div>
                </div>
            </div>

            <div class="accordion" id="permissionGroups">
                @foreach($permissionGroups as $group => $permissions)
                    <div class="accordion-item border rounded-4 mb-3 overflow-hidden" data-permission-group>
                        <h2 class="accordion-header" id="heading-{{ $group }}">
                            <button class="accordion-button {{ $loop->first ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-{{ $group }}">
                                <span class="d-flex justify-content-between align-items-center gap-3 w-100 pe-3">
                                    <span>{{ __(\Illuminate\Support\Str::headline($group)) }}</span>
                                    <span class="badge rounded-pill text-bg-light border">{{ $permissions->count() }}</span>
                                </span>
                            </button>
                        </h2>
                        <div id="collapse-{{ $group }}" class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}">
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
                                                @php
                                                    $permissionRoles = $roles->filter(fn($role) => $role->permissions->contains('slug', $permission->slug));
                                                    $searchText = implode(' ', [
                                                        $group,
                                                        $permission->name,
                                                        $permission->description,
                                                        $permission->slug,
                                                        $permissionRoles->pluck('name')->implode(' '),
                                                    ]);
                                                @endphp
                                                <tr data-permission-row data-permission-search-text="{{ \Illuminate\Support\Str::lower($searchText) }}">
                                                    <td>
                                                        <div class="fw-semibold">{{ __($permission->name) }}</div>
                                                        <code class="small">{{ $permission->slug }}</code>
                                                    </td>
                                                    <td class="text-muted small">{{ __($permission->description) }}</td>
                                                    <td>
                                                        <div class="d-flex flex-wrap gap-2">
                                                            @forelse($permissionRoles as $role)
                                                                <span class="badge rounded-pill text-bg-light border">{{ __($role->name) }}</span>
                                                            @empty
                                                                <span class="text-muted small">{{ __('Not assigned') }}</span>
                                                            @endforelse
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

            <div class="admin-empty-state py-4" data-permission-empty hidden>
                <div class="empty-icon"><i class="mdi mdi-magnify-close"></i></div>
                <h5 class="mb-2">{{ __('No permissions match your search') }}</h5>
                <p class="text-muted mb-0">{{ __('Try a permission name, group, role, or capability keyword.') }}</p>
            </div>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const workspace = document.querySelector('[data-permissions-workspace]');
    if (!workspace) return;

    const input = workspace.querySelector('[data-permission-search]');
    const rows = Array.from(workspace.querySelectorAll('[data-permission-row]'));
    const groups = Array.from(workspace.querySelectorAll('[data-permission-group]'));
    const counter = workspace.querySelector('[data-permission-visible-count]');
    const empty = workspace.querySelector('[data-permission-empty]');

    if (!input) return;

    const normalize = (value) => String(value || '').toLocaleLowerCase().trim();

    const applySearch = () => {
        const query = normalize(input.value);
        let visible = 0;

        rows.forEach((row) => {
            const matches = !query || normalize(row.dataset.permissionSearchText).includes(query);
            row.hidden = !matches;
            if (matches) visible += 1;
        });

        groups.forEach((group) => {
            const groupRows = Array.from(group.querySelectorAll('[data-permission-row]'));
            const hasMatch = groupRows.some((row) => !row.hidden);
            group.hidden = !hasMatch;

            if (query && hasMatch) {
                group.querySelector('.accordion-collapse')?.classList.add('show');
                group.querySelector('.accordion-button')?.classList.remove('collapsed');
            }
        });

        if (counter) counter.textContent = String(visible);
        if (empty) empty.hidden = visible !== 0;
    };

    input.addEventListener('input', applySearch);
});
</script>
@endpush
