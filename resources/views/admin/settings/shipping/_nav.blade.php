<div class="admin-card mb-4">
    <div class="admin-card-body">
        <div class="d-flex flex-wrap gap-2" role="navigation" aria-label="{{ __('Shipping settings') }}">
            <a href="{{ route('admin.settings.shipping.methods') }}" class="btn {{ ($shippingSection ?? '') === 'methods' ? 'btn-primary' : 'btn-light border' }}" @if(($shippingSection ?? '') === 'methods') aria-current="page" @endif>{{ __('Shipping methods') }}</a>
            <a href="{{ route('admin.settings.shipping.zones') }}" class="btn {{ ($shippingSection ?? '') === 'zones' ? 'btn-primary' : 'btn-light border' }}" @if(($shippingSection ?? '') === 'zones') aria-current="page" @endif>{{ __('Zones & cities') }}</a>
            <a href="{{ route('admin.settings.shipping.rates') }}" class="btn {{ ($shippingSection ?? '') === 'rates' ? 'btn-primary' : 'btn-light border' }}" @if(($shippingSection ?? '') === 'rates') aria-current="page" @endif>{{ __('Shipping rates') }}</a>
        </div>
    </div>
</div>
