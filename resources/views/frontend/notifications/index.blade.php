@extends('layouts.app')

@section('title', __('Notifications') . ' | ' . ($storeSettings['store_name'] ?? 'Storefront'))

@section('content')
<section class="py-5 lc-page-shell" data-live-list>
    <div class="container">
        <x-frontend.page-hero :eyebrow="__('Account')" :title="__('Notifications')" :description="__('Keep all payment, delivery, and order updates in one clean account inbox.')" class="mb-4">
            @if(($unreadCount ?? 0) > 0)
                <form method="POST" action="{{ route('notifications.read-all') }}" data-notification-read-all>
                    @csrf @method('PATCH')
                    <button class="btn lc-btn-soft" data-loading-text="{{ __('Updating...') }}">
                        {{ __('Mark all as read') }} <span class="ms-1" data-notification-unread-count>({{ $unreadCount }})</span>
                    </button>
                </form>
            @endif
        </x-frontend.page-hero>
        @include('frontend.account.partials.navigation')

        <form method="GET" action="{{ route('notifications.index') }}" data-live-filter class="d-none"></form>
        @include('frontend.notifications._results')
        <div class="small mt-2 text-center d-none" role="status" aria-live="polite" data-notification-action-status></div>
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
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof window.fetch !== 'function') return;

        const page = document.querySelector('[data-live-list]');
        if (!page) return;

        const actionStatus = page.querySelector('[data-notification-action-status]');
        const setStatus = (message, isError = false) => {
            if (!actionStatus) return;
            actionStatus.textContent = message || '';
            actionStatus.classList.toggle('text-danger', isError);
            actionStatus.classList.toggle('text-success', !isError && Boolean(message));
            actionStatus.classList.toggle('d-none', !message);
        };

        const request = async (form) => {
            const body = new FormData(form);
            const response = await fetch(form.action, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Notification-Live': '1',
                },
                body,
            });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok) {
                const errors = payload.errors || {};
                const firstError = Object.values(errors).flat()[0];
                throw new Error(firstError || payload.message || @json(__('Could not update results. Open the full page to retry.')));
            }
            return payload;
        };

        const updateUnreadCount = (count) => {
            const node = page.querySelector('[data-notification-unread-count]');
            if (node) node.textContent = '(' + String(count) + ')';
            const readAllForm = page.querySelector('[data-notification-read-all]');
            if (readAllForm && Number(count) <= 0) readAllForm.remove();
        };

        const markCardRead = (form, actionUrl = null) => {
            const card = form.closest('[data-notification-card]');
            if (card) card.classList.remove('is-unread');

            if (actionUrl) {
                const link = document.createElement('a');
                link.href = actionUrl;
                link.className = 'btn btn-sm btn-outline-secondary rounded-4';
                link.textContent = form.dataset.viewLabel || @json(__('View update'));
                form.replaceWith(link);
            } else {
                form.remove();
            }
        };

        page.addEventListener('submit', async function (event) {
            const form = event.target.closest('[data-notification-read], [data-notification-read-all]');
            if (!form) return;

            event.preventDefault();
            if (form.dataset.pending === '1') return;
            form.dataset.pending = '1';

            const button = form.querySelector('button');
            if (button) button.disabled = true;
            setStatus(@json(__('Updating...')));

            try {
                const payload = await request(form);
                updateUnreadCount(payload.unread_count || 0);

                if (form.matches('[data-notification-read-all]')) {
                    page.querySelectorAll('[data-notification-read]').forEach(function (itemForm) {
                        markCardRead(itemForm, itemForm.dataset.actionUrl || null);
                    });
                } else {
                    markCardRead(form, payload.action_url || form.dataset.actionUrl || null);
                }

                setStatus(payload.message || @json(__('Results updated.')));

                if (payload.action_url && form.dataset.navigateAfterRead === '1') {
                    window.location.assign(payload.action_url);
                }
            } catch (error) {
                if (button) button.disabled = false;
                delete form.dataset.pending;
                setStatus(error.message || @json(__('Could not update results. Open the full page to retry.')), true);
            }
        });
    });
    </script>
@endpush
