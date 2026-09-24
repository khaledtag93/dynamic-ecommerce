@extends('layouts.app')

@section('title', __('Notifications') . ' | ' . ($storeSettings['store_name'] ?? 'Storefront'))

@section('content')
<section class="py-5 lc-page-shell" data-live-list>
    <div class="container">
        <x-frontend.page-hero :eyebrow="__('Account')" :title="__('Notifications')" :description="__('Keep all payment, delivery, and order updates in one clean account inbox.')" class="mb-4">
            @if(($unreadCount ?? 0) > 0)
                <form method="POST" action="{{ route('notifications.read-all') }}">@csrf @method('PATCH')<button class="btn lc-btn-soft">{{ __('Mark all as read') }} <span class="ms-1">({{ $unreadCount }})</span></button></form>
            @endif
        </x-frontend.page-hero>
        @include('frontend.account.partials.navigation')

        <form method="GET" action="{{ route('notifications.index') }}" data-live-filter class="d-none"></form>
        @include('frontend.notifications._results')
        <div class="small mt-2 text-center" role="status" aria-live="polite" data-live-status
             data-loading="{{ __('Updating results...') }}"
             data-updated="{{ __('Results updated.') }}"
             data-error="{{ __('Could not update results. Open the full page to retry.') }}"></div>
        <a href="{{ route('notifications.index') }}" class="small d-block text-center" data-live-fallback hidden>{{ __('Open full page') }}</a>
    </div>
</section>
@endsection

@push('scripts')
    <script defer src="{{ asset('admin/js/live-list.js') }}"></script>
@endpush
