@extends('layouts.admin')

@section('title', __('Edit work shift') . ' | Admin')

@section('content')
<div class="admin-page-shell">
    <x-admin.page-header :kicker="__('Workforce')" :title="__('Edit work shift')" :description="__('Update the scheduled assignment without overwriting attendance or cash-shift history.')">
        <a href="{{ route('admin.workforce.schedule.index') }}" class="btn btn-light border btn-text-icon"><i class="mdi mdi-arrow-left"></i><span>{{ __('Back to schedule') }}</span></a>
    </x-admin.page-header>

    <div class="admin-card">
        <div class="admin-card-body">
            <form method="POST" action="{{ route('admin.workforce.schedule.update', $shift) }}" data-submit-loading>
                @csrf
                @method('PUT')
                @include('admin.workforce.schedule._form')
                @unless($shift->isCancelled())
                    <div class="admin-form-actions mt-4">
                        <button class="btn btn-primary btn-text-icon" type="submit" data-loading-text="{{ __('Saving...') }}"><i class="mdi mdi-content-save-outline"></i><span>{{ __('Save work shift') }}</span></button>
                    </div>
                @endunless
            </form>
        </div>
    </div>
</div>
@endsection
