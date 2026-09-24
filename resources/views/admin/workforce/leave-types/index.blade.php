@extends('layouts.admin')

@section('title', __('Leave types') . ' | Admin')

@section('content')
<div class="admin-page-shell">
    <x-admin.page-header :kicker="__('Workforce')" :title="__('Leave types')" :description="__('Configure merchant leave policies without hard-coding entitlement assumptions into employee records.')">
        <a href="{{ route('admin.workforce.leave.index') }}" class="btn btn-light border btn-text-icon"><i class="mdi mdi-arrow-left"></i><span>{{ __('Back to leave review') }}</span></a>
        @if(auth()->user()?->hasPermission('workforce.manage'))
            <a href="{{ route('admin.workforce.leave-types.create') }}" class="btn btn-primary btn-text-icon"><i class="mdi mdi-plus"></i><span>{{ __('Add leave type') }}</span></a>
        @endif
    </x-admin.page-header>

    <div class="admin-card">
        <div class="admin-card-body">
            <div class="table-responsive">
                <table class="table admin-table align-middle mb-0">
                    <thead><tr><th>{{ __('Leave type') }}</th><th>{{ __('Payment') }}</th><th>{{ __('Annual entitlement') }}</th><th>{{ __('Usage') }}</th><th>{{ __('Status') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
                    <tbody>
                        @forelse($types as $type)
                            <tr>
                                <td>
                                    <div class="fw-bold">{{ $type->name }}</div>
                                    @if($type->name_ar)<div class="text-muted small" dir="rtl">{{ $type->name_ar }}</div>@endif
                                    <div class="text-muted small">{{ $type->code }}</div>
                                </td>
                                <td>{{ $type->is_paid ? __('Paid') : __('Unpaid') }}</td>
                                <td>{{ number_format((float)$type->default_annual_entitlement_days, 2) }} {{ __('days') }}</td>
                                <td><div>{{ trans_choice(':count request|:count requests', $type->requests_count, ['count' => $type->requests_count]) }}</div><div class="text-muted small">{{ trans_choice(':count adjustment|:count adjustments', $type->adjustments_count, ['count' => $type->adjustments_count]) }}</div></td>
                                <td><span class="badge admin-status-badge {{ $type->is_active ? 'badge-soft-success' : 'badge-soft-secondary' }}">{{ $type->is_active ? __('Active') : __('Inactive') }}</span></td>
                                <td class="text-end">
                                    @if(auth()->user()?->hasPermission('workforce.manage'))
                                        <a href="{{ route('admin.workforce.leave-types.edit', $type) }}" class="btn-table-icon btn-edit" title="{{ __('Edit leave type') }}"><i class="mdi mdi-pencil-outline"></i></a>
                                    @else
                                        <span class="text-muted small">{{ __('View only') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-5">{{ __('No leave types configured yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
