@extends('layouts.app')

@section('title', __('New support request') . ' | ' . ($storeSettings['store_name'] ?? 'Storefront'))

@section('content')
<section class="py-5 lc-page-shell">
    <div class="container">
        <x-frontend.page-hero :eyebrow="__('Help & Support')" :title="__('New support request')" :description="__('Tell us what you need help with. Linking an order helps the team understand the context faster.')" class="mb-4" />
        @include('frontend.account.partials.navigation')

        <div class="lc-card p-4">
            <div class="alert alert-danger rounded-4 d-none mb-3" role="alert" aria-live="polite" data-request-live-status></div>
            <form method="POST" action="{{ route('support.store') }}" class="d-grid gap-3" data-submit-loading data-support-create-live>
                @csrf
                <div>
                    <label class="form-label fw-bold">{{ __('Order (optional)') }}</label>
                    <select name="order_id" class="form-select lc-form-control @error('order_id') is-invalid @enderror">
                        <option value="">{{ __('Not related to an order') }}</option>
                        @foreach($orders as $order)
                            <option value="{{ $order->id }}" @selected((string)old('order_id') === (string)$order->id)>{{ $order->order_number }} · {{ $order->status_label }}</option>
                        @endforeach
                    </select>
                    @error('order_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="form-label fw-bold">{{ __('Subject') }}</label>
                    <input name="subject" value="{{ old('subject') }}" class="form-control lc-form-control @error('subject') is-invalid @enderror" maxlength="180" required>
                    @error('subject')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="form-label fw-bold">{{ __('Category (optional)') }}</label>
                    <input name="category" value="{{ old('category') }}" class="form-control lc-form-control @error('category') is-invalid @enderror" maxlength="80" placeholder="{{ __('Order, payment, delivery, return...') }}">
                    @error('category')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="form-label fw-bold">{{ __('How can we help?') }}</label>
                    <textarea name="message" rows="7" class="form-control lc-form-control @error('message') is-invalid @enderror" maxlength="5000" required>{{ old('message') }}</textarea>
                    @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="d-flex gap-2 justify-content-end">
                    <a href="{{ route('support.index') }}" class="btn lc-btn-soft">{{ __('Cancel') }}</a>
                    <button class="btn lc-btn-primary" data-loading-text="{{ __('Sending request...') }}">{{ __('Create support request') }}</button>
                </div>
            </form>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('form[data-support-create-live]');
    if (!form || typeof window.fetch !== 'function') return;
    const status = document.querySelector('[data-request-live-status]');
    const show = (message, error = false) => {
        if (!status) return;
        status.textContent = message || '';
        status.classList.toggle('d-none', !message);
        status.classList.toggle('text-danger', error);
        status.classList.toggle('text-success', !error && Boolean(message));
    };

    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        event.stopImmediatePropagation();
        const button = event.submitter || form.querySelector('button[type="submit"]');
        if (button) button.disabled = true;
        show('');

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Support-Live': '1',
                    'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value,
                },
                body: new FormData(form),
            });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok) {
                const firstError = Object.values(payload.errors || {}).flat()[0];
                throw new Error(firstError || payload.message || @json(__('Could not create the support request. Please review the form and try again.')));
            }
            show(payload.message || @json(__('Request submitted successfully.')));
            if (payload.redirect_url) window.location.assign(payload.redirect_url);
        } catch (error) {
            show(error.message, true);
            if (button) button.disabled = false;
        }
    }, true);
});
</script>
@endpush
