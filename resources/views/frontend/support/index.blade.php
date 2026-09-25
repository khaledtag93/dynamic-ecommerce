@extends('layouts.app')

@section('title', __('Help & Support') . ' | ' . ($storeSettings['store_name'] ?? 'Storefront'))

@section('content')
<section class="py-5 lc-page-shell" data-live-list>
    <div class="container">
        <x-frontend.page-hero :eyebrow="__('Account')" :title="__('Help & Support')" :description="__('Open a support request and keep every reply connected to the same case.')" class="mb-4" />
        @include('frontend.account.partials.navigation')

        <div class="d-flex justify-content-end mb-3">
            <a href="{{ route('support.create') }}" class="btn lc-btn-primary"><i class="bi bi-plus-lg me-2"></i>{{ __('New support request') }}</a>
        </div>

        <form method="GET" action="{{ route('support.index') }}" data-live-filter class="d-none"></form>
        @include('frontend.support._results')
        <div class="small mt-2 text-center" role="status" aria-live="polite" data-live-status
             data-loading="{{ __('Updating results...') }}"
             data-updated="{{ __('Results updated.') }}"
             data-error="{{ __('Could not update results. Open the full page to retry.') }}"></div>
        <a href="{{ route('support.index') }}" class="small d-block text-center" data-live-fallback hidden>{{ __('Open full page') }}</a>
    </div>
</section>
@endsection

@push('scripts')
    <script defer src="{{ asset('admin/js/live-list.js') }}"></script>
@endpush
