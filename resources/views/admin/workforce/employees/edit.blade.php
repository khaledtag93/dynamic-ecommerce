@extends('layouts.admin')

@section('title', __('Edit employee') . ' | Admin')

@section('content')
<div class="admin-page-shell">
    <x-admin.page-header :kicker="__('Workforce')" :title="__('Edit employee')" :description="__('Maintain employment details while preserving the linked staff identity and attendance history.')">
        <a href="{{ route('admin.workforce.employees.index') }}" class="btn btn-light border btn-text-icon"><i class="mdi mdi-arrow-left"></i><span>{{ __('Back to employees') }}</span></a>
        <a href="{{ route('admin.workforce.attendance.index', ['search' => $employee->employee_code]) }}" class="btn btn-light border btn-text-icon"><i class="mdi mdi-calendar-clock-outline"></i><span>{{ __('Attendance history') }}</span></a>
    </x-admin.page-header>

    <div class="admin-card">
        <div class="admin-card-body">
            <form method="POST" action="{{ route('admin.workforce.employees.update', $employee) }}" data-submit-loading>
                @csrf
                @method('PUT')
                @include('admin.workforce.employees._form')
                <div class="admin-form-actions mt-4">
                    <button type="submit" class="btn btn-primary btn-text-icon" data-loading-text="{{ __('Saving...') }}"><i class="mdi mdi-content-save-outline"></i><span>{{ __('Save employee') }}</span></button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
