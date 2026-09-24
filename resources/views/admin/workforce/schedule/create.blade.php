@extends('layouts.admin')

@section('title', __('Add work shift') . ' | Admin')

@section('content')
<div class="admin-page-shell">
    <x-admin.page-header :kicker="__('Workforce')" :title="__('Add work shift')" :description="__('Schedule one employee with overlap protection and publish only when the shift is ready.')">
        <a href="{{ route('admin.workforce.schedule.index') }}" class="btn btn-light border btn-text-icon"><i class="mdi mdi-arrow-left"></i><span>{{ __('Back to schedule') }}</span></a>
    </x-admin.page-header>

    <div class="admin-card">
        <div class="admin-card-body">
            <form method="POST" action="{{ route('admin.workforce.schedule.store') }}" data-submit-loading>
                @csrf
                @include('admin.workforce.schedule._form')
                <div class="admin-form-actions mt-4">
                    <button class="btn btn-primary btn-text-icon" type="submit" data-loading-text="{{ __('Saving...') }}"><i class="mdi mdi-content-save-outline"></i><span>{{ __('Create work shift') }}</span></button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
