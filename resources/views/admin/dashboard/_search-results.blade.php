@if($q !== '')
    <section class="admin-card mb-4" id="dashboard-search" aria-labelledby="dashboard-search-title">
        <div class="admin-card-body">
            <div class="admin-home-section-heading mb-3">
                <div><div class="admin-kicker">{{ __('Quick lookup') }}</div><h2 id="dashboard-search-title">{{ __('Search results') }}</h2><p class="text-muted small mb-0">{{ __('Showing quick matches for') }} <strong>{{ $q }}</strong></p></div>
                <a href="{{ route('admin.dashboard') }}" class="btn btn-light border btn-sm" data-dashboard-search-clear>{{ __('Clear search') }}</a>
            </div>
            <div class="row g-3">
                @foreach([
                    'orders' => ['label' => __('Orders'), 'route' => 'admin.orders.show', 'field' => 'order_number', 'permission' => 'orders.view'],
                    'products' => ['label' => __('Products'), 'route' => 'admin.products.edit', 'field' => 'name', 'permission' => 'catalog.manage'],
                    'customers' => ['label' => __('Customers'), 'route' => 'admin.customers.show', 'field' => 'name', 'permission' => 'customers.manage'],
                    'coupons' => ['label' => __('Coupons'), 'route' => 'admin.coupons.edit', 'field' => 'code', 'permission' => 'promotions.manage'],
                    'categories' => ['label' => __('Categories'), 'route' => 'admin.categories.edit', 'field' => 'name', 'permission' => 'catalog.manage'],
                ] as $key => $meta)
                    @if($can($meta['permission']))
                        <div class="col-md-6 col-xl-4">
                            <div class="admin-home-search-group h-100">
                                <h3>{{ $meta['label'] }}</h3>
                                @forelse($searchResults[$key] as $item)
                                    <a href="{{ route($meta['route'], $item) }}">{{ data_get($item, $meta['field']) }} <i class="mdi mdi-arrow-top-right"></i></a>
                                @empty
                                    <p class="text-muted small mb-0">{{ __('No matches yet.') }}</p>
                                @endforelse
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    </section>
@endif