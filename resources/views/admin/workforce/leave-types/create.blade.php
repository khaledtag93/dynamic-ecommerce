@extends('layouts.admin')

@section('title', __('Add leave type') . ' | Admin')

@section('content')
<div class="admin-page-shell">
    <x-admin.page-header :kicker="__('Workforce')" :title="__('Add leave type')" :description="__('Create a leave policy and define its default annual entitlement.')">
        <a href="{{ route('admin.workforce.leave-types.index') }}" class="btn btn-light border btn-text-icon"><i class="mdi mdi-arrow-left"></i><span>{{ __('Back to leave types') }}</span></a>
    </x-admin.page-header>
    <div class="admin-card"><div class="admin-card-body">
        <form method="POST" action="{{ route('admin.workforce.leave-types.store') }}" data-submit-loading>
            @csrf
            @include('admin.workforce.leave-types._form')
            <div class="admin-form-actions mt-4"><button class="btn btn-primary btn-text-icon" data-loading-text="{{ __('Saving...') }}"><i class="mdi mdi-content-save-outline"></i><span>{{ __('Create leave type') }}</span></button></div>
        </form>
    </div></div>
</div>
@endsection
