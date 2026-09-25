@extends('layouts.app')

@section('title', __('Request a return') . ' | ' . ($storeSettings['store_name'] ?? 'Storefront'))

@section('content')
<section class="py-5 lc-page-shell">
    <div class="container">
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-4">
            <div>
                <div class="text-uppercase small text-muted fw-bold">{{ __('Returns') }}</div>
                <h1 class="lc-section-title mb-1">{{ __('Request a return') }}</h1>
                <p class="text-muted mb-0">{{ __('Order :order', ['order' => $order->order_number]) }}</p>
            </div>
            <a href="{{ route('orders.show', $order) }}" class="btn lc-btn-soft">{{ __('Back to order') }}</a>
        </div>

        @if($errors->any())
            <div class="alert alert-danger rounded-4">
                <div class="fw-bold mb-1">{{ __('Please review the return request') }}</div>
                <div>{{ $errors->first() }}</div>
            </div>
        @endif

        <div class="alert alert-danger rounded-4 d-none" role="alert" aria-live="polite" data-request-live-status></div>

        <form method="POST" action="{{ route('returns.store', $order) }}" data-submit-loading data-return-create-live>
            @csrf
            <div class="lc-card p-4 mb-4">
                <h4 class="fw-bold mb-2">{{ __('Choose items and quantities') }}</h4>
                <p class="text-muted mb-4">{{ __('Enter zero for items you are not returning. The available quantity already accounts for other active return requests.') }}</p>

                <div class="d-grid gap-3">
                    @foreach($order->items as $index => $item)
                        @php($remaining = (int) ($remainingByItem[$item->id] ?? 0))
                        <article class="border rounded-4 p-3 {{ $remaining < 1 ? 'opacity-75' : '' }}">
                            <input type="hidden" name="items[{{ $index }}][order_item_id]" value="{{ $item->id }}">
                            <div class="row g-3 align-items-start">
                                <div class="col-lg-4">
                                    <div class="d-flex gap-3 align-items-center">
                                        <img src="{{ $item->image_url ?: asset('images/storefront-placeholder.svg') }}" alt="{{ $item->product_name }}" width="72" height="72" class="rounded-4" style="object-fit:cover;">
                                        <div>
                                            <div class="fw-bold">{{ $item->product_name }}</div>
                                            @if($item->variant_name)<div class="text-muted small">{{ $item->variant_name }}</div>@endif
                                            <div class="text-muted small">{{ __('Ordered') }}: {{ $item->quantity }} · {{ __('Returnable') }}: {{ $remaining }}</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-sm-4 col-lg-2">
                                    <label class="form-label fw-semibold">{{ __('Quantity') }}</label>
                                    <input type="number" min="0" max="{{ $remaining }}" name="items[{{ $index }}][quantity]" value="{{ old("items.$index.quantity", 0) }}" class="form-control" {{ $remaining < 1 ? 'disabled' : '' }}>
                                </div>
                                <div class="col-sm-8 col-lg-3">
                                    <label class="form-label fw-semibold">{{ __('Reason') }}</label>
                                    <select name="items[{{ $index }}][reason_code]" class="form-select" {{ $remaining < 1 ? 'disabled' : '' }}>
                                        <option value="">{{ __('Select reason') }}</option>
                                        @foreach($reasonOptions as $value => $label)
                                            <option value="{{ $value }}" @selected(old("items.$index.reason_code") === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-lg-3">
                                    <label class="form-label fw-semibold">{{ __('Requested resolution') }}</label>
                                    <select name="items[{{ $index }}][requested_resolution]" class="form-select" {{ $remaining < 1 ? 'disabled' : '' }}>
                                        @foreach($resolutionOptions as $value => $label)
                                            <option value="{{ $value }}" @selected(old("items.$index.requested_resolution", 'refund') === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold">{{ __('Reason details (optional)') }}</label>
                                    <textarea name="items[{{ $index }}][reason_details]" rows="2" maxlength="1000" class="form-control" {{ $remaining < 1 ? 'disabled' : '' }}>{{ old("items.$index.reason_details") }}</textarea>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>

            <div class="lc-card p-4 mb-4">
                <label class="form-label fw-semibold">{{ __('Additional return notes (optional)') }}</label>
                <textarea name="customer_notes" rows="4" maxlength="2000" class="form-control">{{ old('customer_notes') }}</textarea>
                <div class="text-muted small mt-2">{{ __('Submitting a return request does not automatically refund money or restore inventory. The store reviews the request first.') }}</div>
            </div>

            <div class="d-flex justify-content-end gap-2 flex-wrap">
                <a href="{{ route('orders.show', $order) }}" class="btn lc-btn-soft">{{ __('Cancel') }}</a>
                <button class="btn lc-btn-primary" data-loading-text="{{ __('Submitting...') }}">{{ __('Submit return request') }}</button>
            </div>
        </form>
    </div>
</section>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('form[data-return-create-live]');
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
                    'X-Return-Live': '1',
                    'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value,
                },
                body: new FormData(form),
            });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok) {
                const firstError = Object.values(payload.errors || {}).flat()[0];
                throw new Error(firstError || payload.message || @json(__('Could not submit the return request. Please review the form and try again.')));
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
