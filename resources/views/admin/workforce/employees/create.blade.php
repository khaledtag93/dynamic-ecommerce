@extends('layouts.admin')

@section('title', __('Add employee') . ' | Admin')

@section('content')
<div class="admin-page-shell">
    <x-admin.page-header :kicker="__('Workforce')" :title="__('Add employee')" :description="__('Link an existing staff account to a workforce profile without changing its login or role permissions.')">
        <a href="{{ route('admin.workforce.employees.index') }}" class="btn btn-light border btn-text-icon"><i class="mdi mdi-arrow-left"></i><span>{{ __('Back to employees') }}</span></a>
    </x-admin.page-header>

    <div class="admin-card">
        <div class="admin-card-body">
            @if($candidateUsers->isEmpty())
                <div class="admin-empty-state py-4">
                    <div class="empty-icon"><i class="mdi mdi-account-alert-outline"></i></div>
                    <h5 class="mb-2">{{ __('No unlinked staff accounts') }}</h5>
                    <p class="text-muted mb-0">{{ __('Every current staff account already has an employee profile, or no staff account is available yet.') }}</p>
                </div>
            @else
                <form method="POST" action="{{ route('admin.workforce.employees.store') }}" data-submit-loading>
                    @csrf
                    @include('admin.workforce.employees._form')
                    <div class="admin-form-actions mt-4">
                        <button type="submit" class="btn btn-primary btn-text-icon" data-loading-text="{{ __('Saving...') }}"><i class="mdi mdi-content-save-outline"></i><span>{{ __('Create employee') }}</span></button>
                    </div>
                </form>
            @endif
        </div>
    </div>
</div>
@endsection
