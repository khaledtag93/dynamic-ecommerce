@extends('layouts.app')

@section('title', ($address ? __('Edit address') : __('Add address')) . ' | ' . ($storeSettings['store_name'] ?? 'Storefront'))

@section('content')
<section class="py-5 lc-page-shell">
    <div class="container">
        <x-frontend.page-hero :eyebrow="__('Address book')" :title="$address ? __('Edit address') : __('Add address')" :description="__('These details can fill checkout. Existing orders keep the address used when they were placed.')" class="mb-4" />
        @include('frontend.account.partials.navigation')
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <form method="POST" action="{{ $address ? route('account.addresses.update', $address) : route('account.addresses.store') }}" class="lc-card p-4 p-lg-5" data-submit-loading data-address-save-live>
                    @csrf
                    @if($address) @method('PATCH') @endif
                    <div class="small mb-3 d-none" role="status" aria-live="polite" data-address-save-status></div>
                    <h2 class="h4 fw-bold mb-3">{{ __('Contact and delivery') }}</h2>
                    <div class="row g-3 mb-4">
                        @foreach([
                            ['label', __('Address label'), 'text', 'off', 6],
                            ['recipient_name', __('Recipient name'), 'text', 'name', 6],
                            ['phone', __('Phone number'), 'tel', 'tel', 6],
                            ['country', __('Country'), 'text', 'country-name', 6],
                            ['address_line_1', __('Address line 1'), 'text', 'address-line1', 12],
                            ['address_line_2', __('Address line 2 (optional)'), 'text', 'address-line2', 12],
                            ['city', __('City'), 'text', 'address-level2', 4],
                            ['state', __('State / Area'), 'text', 'address-level1', 4],
                            ['postal_code', __('Postal code'), 'text', 'postal-code', 4],
                        ] as [$field, $label, $type, $autocomplete, $width])
                            <div class="col-md-{{ $width }}">
                                <label for="address_{{ $field }}" class="form-label fw-bold">{{ $label }}</label>
                                <input id="address_{{ $field }}" name="{{ $field }}" type="{{ $type }}" autocomplete="{{ $autocomplete }}"
                                       value="{{ old($field, $address?->{$field} ?? ($field === 'country' ? 'Egypt' : '')) }}"
                                       class="form-control lc-form-control @error($field) is-invalid @enderror" @if(in_array($field, ['label', 'recipient_name', 'phone', 'country', 'address_line_1', 'city'])) required @endif>
                                @error($field)<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        @endforeach
                    </div>
                    <div class="border-top pt-4 mb-4">
                        <h2 class="h5 fw-bold mb-2">{{ __('Use as default') }}</h2>
                        <p class="text-muted small">{{ __('The first saved address becomes your shipping and billing default. You can change either later.') }}</p>
                        <div class="form-check mb-2">
                            <input id="defaultShipping" class="form-check-input" type="checkbox" name="is_default_shipping" value="1" @checked(old('is_default_shipping', $address?->is_default_shipping ?? false))>
                            <label class="form-check-label" for="defaultShipping">{{ __('Default shipping address') }}</label>
                        </div>
                        <div class="form-check">
                            <input id="defaultBilling" class="form-check-input" type="checkbox" name="is_default_billing" value="1" @checked(old('is_default_billing', $address?->is_default_billing ?? false))>
                            <label class="form-check-label" for="defaultBilling">{{ __('Default billing address') }}</label>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" class="btn lc-btn-primary">{{ __('Save address') }}</button>
                        <a href="{{ route('account.addresses.index') }}" class="btn lc-btn-soft">{{ __('Cancel') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('form[data-address-save-live]');
    if (!form || typeof window.fetch !== 'function') return;
    const status = form.querySelector('[data-address-save-status]');
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
                method: @json($address ? 'PATCH' : 'POST'),
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Address-Live': '1',
                    'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value,
                },
                body: new FormData(form),
            });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok) {
                const firstError = Object.values(payload.errors || {}).flat()[0];
                throw new Error(firstError || payload.message || @json(__('Could not save the address. Please review the form and try again.')));
            }
            show(payload.message || @json(__('Address saved.')));
            if (payload.redirect_url) window.location.assign(payload.redirect_url);
        } catch (error) {
            show(error.message, true);
            if (button) button.disabled = false;
        }
    }, true);
});
</script>
@endpush
