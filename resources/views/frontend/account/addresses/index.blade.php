@extends('layouts.app')

@section('title', __('Address book') . ' | ' . ($storeSettings['store_name'] ?? 'Storefront'))

@section('content')
<section class="py-5 lc-page-shell">
    <div class="container">
        <x-frontend.page-hero :eyebrow="__('Account')" :title="__('Address book')" :description="__('Save delivery and billing details for your next order.')" class="mb-4">
            <a href="{{ route('account.addresses.create') }}" class="btn lc-btn-primary"><i class="bi bi-plus-lg me-1"></i>{{ __('Add address') }}</a>
        </x-frontend.page-hero>
        @include('frontend.account.partials.navigation')

        <div class="small text-muted mb-3 d-none" data-address-live-status role="status" aria-live="polite"></div>

        <div class="row g-3" data-address-list>
            @forelse($addresses as $address)
                <div class="col-md-6 col-xl-4" data-address-card="{{ $address->id }}">
                    <div class="lc-card p-4 h-100 d-flex flex-column">
                        <div class="d-flex justify-content-between gap-2 align-items-start mb-3">
                            <h2 class="h5 fw-bold mb-0">{{ $address->label }}</h2>
                            <i class="bi bi-geo-alt text-muted" aria-hidden="true"></i>
                        </div>
                        <div class="d-flex flex-wrap gap-2 mb-3" data-address-defaults>
                            <span class="lc-status-badge lc-badge-success" data-default-shipping @if(!$address->is_default_shipping) hidden @endif>{{ __('Default shipping') }}</span>
                            <span class="lc-status-badge lc-badge-processing" data-default-billing @if(!$address->is_default_billing) hidden @endif>{{ __('Default billing') }}</span>
                        </div>
                        <div class="fw-semibold">{{ $address->recipient_name }}</div>
                        <div class="text-muted mb-3" dir="auto">{{ $address->phone }}</div>
                        <address class="text-muted mb-4" dir="auto">
                            {{ $address->address_line_1 }}
                            @if($address->address_line_2), {{ $address->address_line_2 }}@endif
                            <br>
                            {{ $address->city }}
                            @if($address->state), {{ $address->state }}@endif
                            @if($address->postal_code) {{ $address->postal_code }}@endif
                            <br>
                            {{ $address->country }}
                        </address>
                        <div class="d-flex gap-2 flex-wrap mt-auto">
                            <a href="{{ route('account.addresses.edit', $address) }}" class="btn lc-btn-soft btn-sm">{{ __('Edit address') }}</a>
                            <form method="POST" action="{{ route('account.addresses.destroy', $address) }}"
                                  data-confirm-title="{{ __('Delete address') }}"
                                  data-confirm-message="{{ __('Delete this saved address?') }}"
                                  data-confirm-subtitle="{{ __('Existing orders keep their original delivery details.') }}"
                                  data-confirm-ok="{{ __('Delete address') }}"
                                  data-address-delete-live>
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-outline-danger btn-sm">{{ __('Delete') }}</button>
                            </form>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12">
                    <div class="lc-card lc-empty-state">
                        <div class="lc-empty-icon"><i class="bi bi-geo-alt"></i></div>
                        <h2 class="h4 fw-bold">{{ __('No saved addresses yet') }}</h2>
                        <p class="text-muted">{{ __('Add an address to fill checkout details more quickly next time.') }}</p>
                        <a href="{{ route('account.addresses.create') }}" class="btn lc-btn-primary">{{ __('Add address') }}</a>
                    </div>
                </div>
            @endforelse
        </div>

        <template data-address-empty-template>
            <div class="col-12">
                <div class="lc-card lc-empty-state">
                    <div class="lc-empty-icon"><i class="bi bi-geo-alt"></i></div>
                    <h2 class="h4 fw-bold">{{ __('No saved addresses yet') }}</h2>
                    <p class="text-muted">{{ __('Add an address to fill checkout details more quickly next time.') }}</p>
                    <a href="{{ route('account.addresses.create') }}" class="btn lc-btn-primary">{{ __('Add address') }}</a>
                </div>
            </div>
        </template>
    </div>
</section>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const list = document.querySelector('[data-address-list]');
    const status = document.querySelector('[data-address-live-status]');
    const emptyTemplate = document.querySelector('[data-address-empty-template]');

    document.querySelectorAll('form[data-address-delete-live]').forEach(function (form) {
        form.addEventListener('submit', async function (event) {
            if (form.dataset.confirmed !== '1') return;

            event.preventDefault();
            event.stopImmediatePropagation();

            const button = event.submitter || form.querySelector('button[type="submit"]');
            if (button) button.disabled = true;

            try {
                const response = await fetch(form.action, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-Address-Live': '1',
                        'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value,
                    },
                    credentials: 'same-origin',
                });

                if (!response.ok) throw new Error('address-delete-failed');

                const payload = await response.json();
                form.closest('[data-address-card]')?.remove();

                (payload.addresses || []).forEach(function (address) {
                    const card = list?.querySelector('[data-address-card="' + address.id + '"]');
                    if (!card) return;
                    const shipping = card.querySelector('[data-default-shipping]');
                    const billing = card.querySelector('[data-default-billing]');
                    if (shipping) shipping.hidden = !address.is_default_shipping;
                    if (billing) billing.hidden = !address.is_default_billing;
                });

                if (list && (payload.addresses || []).length === 0 && emptyTemplate) {
                    list.innerHTML = emptyTemplate.innerHTML;
                }

                if (status) {
                    status.textContent = payload.message || @json(__('Address deleted.'));
                    status.classList.remove('d-none');
                }
            } catch (error) {
                form.submit();
            }
        }, true);
    });
});
</script>
@endpush
