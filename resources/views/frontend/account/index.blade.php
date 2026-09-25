@extends('layouts.app')

@section('title', __('My account') . ' | ' . ($storeSettings['store_name'] ?? 'Storefront'))

@section('content')
<section class="py-5 lc-page-shell">
    <div class="container">
        <x-frontend.page-hero :eyebrow="__('Account')" :title="__('My account')" :description="__('Manage your details and keep track of your orders in one place.')" class="mb-4" />
        @include('frontend.account.partials.navigation')

        <div class="row g-3 mb-4">
            <div class="col-md-6 col-xl-3"><a href="{{ route('orders.index') }}" class="lc-card lc-account-shortcut h-100"><i class="bi bi-receipt"></i><span><strong>{{ __('My Orders') }}</strong><small>{{ __('Orders: :count', ['count' => $ordersCount]) }}</small></span><i class="bi bi-arrow-up-right"></i></a></div>
            <div class="col-md-6 col-xl-3"><a href="{{ route('account.addresses.index') }}" class="lc-card lc-account-shortcut h-100"><i class="bi bi-geo-alt"></i><span><strong>{{ __('Address book') }}</strong><small>{{ __('Saved addresses: :count', ['count' => $addressesCount]) }}</small></span><i class="bi bi-arrow-up-right"></i></a></div>
            <div class="col-md-6 col-xl-3"><a href="{{ route('notifications.index') }}" class="lc-card lc-account-shortcut h-100"><i class="bi bi-bell"></i><span><strong>{{ __('Notifications') }}</strong><small>{{ __(':count unread', ['count' => $unreadCount]) }}</small></span><i class="bi bi-arrow-up-right"></i></a></div>
            <div class="col-md-6 col-xl-3"><a href="{{ route('support.index') }}" class="lc-card lc-account-shortcut h-100"><i class="bi bi-headset"></i><span><strong>{{ __('Help & Support') }}</strong><small>{{ __('Open and track support requests') }}</small></span><i class="bi bi-arrow-up-right"></i></a></div>
        </div>

        <div class="small mb-3 d-none" role="status" aria-live="polite" data-account-live-status></div>

        <div class="row g-4 align-items-start">
            <div class="col-lg-7">
                <div class="lc-card p-4 mb-4">
                    <h2 class="h4 fw-bold mb-1">{{ __('Profile details') }}</h2>
                    <p class="text-muted mb-4">{{ __('Update your name or email. Confirm your password when changing your email.') }}</p>
                    <form method="POST" action="{{ route('account.profile.update') }}" class="d-grid gap-3" data-submit-loading data-account-live="profile">
                        @csrf @method('PATCH')
                        <div>
                            <label for="accountName" class="form-label fw-bold">{{ __('Full name') }}</label>
                            <input id="accountName" name="name" autocomplete="name" value="{{ old('name', auth()->user()->name) }}" class="form-control lc-form-control @error('name') is-invalid @enderror" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label for="accountEmail" class="form-label fw-bold">{{ __('Email address') }}</label>
                            <input id="accountEmail" type="email" name="email" autocomplete="email" value="{{ old('email', auth()->user()->email) }}" class="form-control lc-form-control @error('email') is-invalid @enderror" required>
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label for="accountEmailPassword" class="form-label fw-bold">{{ __('Current password for email change') }}</label>
                            <input id="accountEmailPassword" type="password" name="current_password" autocomplete="current-password" class="form-control lc-form-control @error('current_password') is-invalid @enderror">
                            @error('current_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div><button type="submit" class="btn lc-btn-primary">{{ __('Save profile') }}</button></div>
                    </form>
                </div>

                <div class="lc-card p-4">
                    <h2 class="h4 fw-bold mb-1">{{ __('Change password') }}</h2>
                    <p class="text-muted mb-4">{{ __('Use your current password to protect account changes.') }}</p>
                    <form method="POST" action="{{ route('account.password.update') }}" class="d-grid gap-3" data-submit-loading data-account-live="password">
                        @csrf @method('PATCH')
                        <div>
                            <label for="passwordCurrent" class="form-label fw-bold">{{ __('Current password') }}</label>
                            <input id="passwordCurrent" type="password" name="current_password" autocomplete="current-password" class="form-control lc-form-control @error('current_password', 'passwordUpdate') is-invalid @enderror" required>
                            @error('current_password', 'passwordUpdate')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label for="passwordNew" class="form-label fw-bold">{{ __('New password') }}</label>
                            <input id="passwordNew" type="password" name="password" autocomplete="new-password" class="form-control lc-form-control @error('password', 'passwordUpdate') is-invalid @enderror" required minlength="8">
                            @error('password', 'passwordUpdate')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label for="passwordConfirm" class="form-label fw-bold">{{ __('Confirm new password') }}</label>
                            <input id="passwordConfirm" type="password" name="password_confirmation" autocomplete="new-password" class="form-control lc-form-control" required minlength="8">
                        </div>
                        <div><button type="submit" class="btn lc-btn-soft">{{ __('Update password') }}</button></div>
                    </form>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="lc-card p-4">
                    <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
                        <h2 class="h4 fw-bold mb-0">{{ __('Recent orders') }}</h2>
                        <a href="{{ route('orders.index') }}">{{ __('View all') }}</a>
                    </div>
                    @forelse($recentOrders as $order)
                        <a href="{{ route('orders.show', $order) }}" class="lc-account-order">
                            <span><strong>{{ $order->order_number }}</strong><small>{{ optional($order->placed_at)->format('d M Y') ?: $order->created_at->format('d M Y') }}</small></span>
                            <span class="lc-status-badge {{ $order->status === \App\Models\Order::STATUS_COMPLETED ? 'lc-badge-success' : ($order->status === \App\Models\Order::STATUS_CANCELLED ? 'lc-badge-danger' : 'lc-badge-processing') }}">{{ $order->status_label }}</span>
                        </a>
                    @empty
                        <p class="text-muted mb-3">{{ __('Your orders will appear here after checkout.') }}</p>
                        <a href="{{ route('frontend.home') }}" class="btn lc-btn-soft">{{ __('Continue shopping') }}</a>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof window.fetch !== 'function') return;
    const statusNode = document.querySelector('[data-account-live-status]');

    const setStatus = (message, isError = false) => {
        if (!statusNode) return;
        statusNode.textContent = message || '';
        statusNode.classList.toggle('d-none', !message);
        statusNode.classList.toggle('text-danger', isError);
        statusNode.classList.toggle('text-success', !isError && Boolean(message));
    };

    document.querySelectorAll('form[data-account-live]').forEach(function (form) {
        form.addEventListener('submit', async function (event) {
            event.preventDefault();
            event.stopImmediatePropagation();

            const button = event.submitter || form.querySelector('button[type="submit"]');
            if (button) button.disabled = true;
            setStatus('');

            try {
                const response = await fetch(form.action, {
                    method: 'PATCH',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-Account-Live': '1',
                        'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value,
                    },
                    body: new FormData(form),
                });
                const payload = await response.json().catch(() => ({}));

                if (!response.ok) {
                    const firstError = Object.values(payload.errors || {}).flat()[0];
                    throw new Error(firstError || payload.message || @json(__('Could not save your changes. Please try again.')));
                }

                setStatus(payload.message || @json(__('Changes saved.')));
                if (form.dataset.accountLive === 'password') form.reset();
                if (form.dataset.accountLive === 'profile') {
                    const password = form.querySelector('[name="current_password"]');
                    if (password) password.value = '';
                }
            } catch (error) {
                setStatus(error.message, true);
            } finally {
                if (button) button.disabled = false;
            }
        }, true);
    });
});
</script>
@endpush
