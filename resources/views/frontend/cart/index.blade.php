@extends('layouts.app')

@section('title', __('Cart') . ' | ' . ($storeSettings['store_name'] ?? 'Storefront'))

@section('content')
<section class="py-5 lc-page-shell">
    <div class="container">
        <div class="lc-cart-shell">
            <div class="lc-cart-toolbar">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
                    <div>
                        <div class="text-uppercase small text-muted fw-bold">{{ __('Cart') }}</div>
                        <h1 class="lc-section-title mb-2">{{ __('Review your cart') }}</h1>
                        <p class="text-muted mb-0">{{ __('Everything here is ready for a quick move into checkout once quantities and totals look right.') }}</p>
                    </div>
                    <span class="lc-badge"><i class="bi bi-bag-check"></i>{{ __('Checkout-ready basket') }}</span>
                </div>

                <div class="lc-progress-strip">
                    <div class="lc-progress-step is-active lc-state-pulse">
                        <span class="lc-progress-step__dot">1</span>
                        <div>
                            <div class="lc-progress-step__title">{{ __('Cart review') }}</div>
                            <div class="lc-progress-step__copy">{{ __('Quantities, coupon, and totals.') }}</div>
                        </div>
                    </div>
                    <div class="lc-progress-step">
                        <span class="lc-progress-step__dot">2</span>
                        <div>
                            <div class="lc-progress-step__title">{{ __('Checkout details') }}</div>
                            <div class="lc-progress-step__copy">{{ __('Address, payment, and final review.') }}</div>
                        </div>
                    </div>
                    <div class="lc-progress-step">
                        <span class="lc-progress-step__dot">3</span>
                        <div>
                            <div class="lc-progress-step__title">{{ __('Order placed') }}</div>
                            <div class="lc-progress-step__copy">{{ __('Confirmation and next steps.') }}</div>
                        </div>
                    </div>
                </div>

                @if(!empty($shippingGoal))
                    <div class="lc-card p-3 p-lg-4 cart-aov-progress">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                            <strong>{{ $shippingGoal['qualified'] ? __('Free shipping unlocked') : __('Get closer to free shipping') }}</strong>
                            <span class="lc-badge"><i class="bi bi-truck"></i>{{ __('Goal') }}: EGP {{ number_format($shippingGoal['goal'], 2) }}</span>
                        </div>
                        <div class="text-muted small mb-3">
                            {{ $shippingGoal['qualified'] ? __('Nice. This order already crossed the shipping target, which helps conversion and perceived value.') : __('Add only :amount more to unlock the shipping goal and raise the basket value naturally.', ['amount' => 'EGP ' . number_format($shippingGoal['remaining'], 2)]) }}
                        </div>
                        <div class="cart-aov-progress__bar"><span style="width: {{ $shippingGoal['progress'] }}%"></span></div>
                    </div>
                @endif

                @include('frontend.partials.behavioral-offers', ['cards' => $behavioralOffers['cards'] ?? collect()])
            </div>

            @if($cart['items']->isEmpty())
                <div class="lc-empty-panel">
                    <div class="lc-section-empty__icon"><i class="bi bi-bag-x"></i></div>
                    <h2 class="h4 fw-bold mb-2">{{ __('Your cart is empty') }}</h2>
                    <p class="text-muted mb-4">{{ __('Start with one strong product page, then use the cart summary to lead into checkout.') }}</p>
                    <a href="{{ route('frontend.home') }}" class="btn lc-btn-primary">{{ __('Browse products') }}</a>
                </div>
            @else
                <div class="row g-4 align-items-start">
                    <div class="col-lg-8">
                        <div class="lc-card p-3 p-lg-4">
                            <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-4">
                                <div>
                                    <h4 class="fw-bold mb-1">{{ __('Cart items') }}</h4>
                                    <div class="text-muted small">{{ __('Review product details, quantity, and line totals before continuing.') }}</div>
                                </div>
                                <span class="lc-badge"><i class="bi bi-box-seam"></i>{{ __(':count items', ['count' => $cart['items_count']]) }}</span>
                            </div>

                            <div class="d-grid gap-3">
                                @foreach($cart['items'] as $item)
                                    @php($productSlug = $item->meta['product_slug'] ?? optional($item->product)->slug)
                                    <article class="lc-cart-item">
                                        @if($productSlug)
                                            <a href="{{ route('frontend.products.show', $productSlug) }}" class="lc-cart-item__media">
                                                <img src="{{ $item->image_url ?: 'https://via.placeholder.com/100x100?text=No+Image' }}" alt="{{ $item->product_name }}">
                                            </a>
                                        @else
                                            <div class="lc-cart-item__media">
                                                <img src="{{ $item->image_url ?: 'https://via.placeholder.com/100x100?text=No+Image' }}" alt="{{ $item->product_name }}">
                                            </div>
                                        @endif

                                        <div class="lc-cart-item__meta">
                                            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                                                <div>
                                                    @if($productSlug)
                                                        <a href="{{ route('frontend.products.show', $productSlug) }}" class="lc-cart-item__title text-dark">{{ $item->product_name }}</a>
                                                    @else
                                                        <div class="lc-cart-item__title text-dark">{{ $item->product_name }}</div>
                                                    @endif
                                                    @if($item->variant_name)
                                                        <div class="lc-cart-item__sub">{{ $item->variant_name }}</div>
                                                    @endif
                                                </div>
                                                <span class="lc-badge">{{ __('Price') }}: EGP {{ number_format($item->unit_price, 2) }}</span>
                                            </div>

                                            <div class="lc-inline-note">
                                                <i class="bi bi-info-circle"></i>
                                                <span>{{ __('You can update quantity before checkout. Totals refresh automatically when you save.') }}</span>
                                            </div>

                                            <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
                                                <form method="POST" action="{{ route('cart.update', $item) }}" class="d-flex align-items-center gap-2" data-submit-loading>
                                                    @csrf
                                                    @method('PATCH')
                                                    <div class="lc-qty-shell">
                                                        <span class="small text-muted fw-bold">{{ __('Qty') }}</span>
                                                        <input type="number" name="quantity" min="1" value="{{ $item->quantity }}" class="form-control lc-form-control text-center">
                                                    </div>
                                                    <button type="submit" class="btn lc-btn-soft" data-loading-text="{{ __('Updating...') }}">{{ __('Update') }}</button>
                                                </form>

                                                <div class="lc-cart-item__actions d-flex align-items-center gap-2 ms-auto">
                                                    <div class="text-end">
                                                        <div class="small text-muted">{{ __('Line total') }}</div>
                                                        <div class="fw-bold fs-5">EGP {{ number_format($item->line_total, 2) }}</div>
                                                    </div>
                                                    <form method="POST" action="{{ route('cart.destroy', $item) }}" data-submit-loading>
                                                        @csrf
                                                        @method('DELETE')
                                                        <button class="btn lc-btn-danger-soft" type="submit" data-loading-text="{{ __('Removing...') }}">{{ __('Remove') }}</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="lc-summary-card-sticky d-grid gap-3">
                            <div class="lc-card p-4 lc-coupon-box">
                                <div class="d-flex justify-content-between align-items-center gap-3 mb-3">
                                    <div>
                                        <h4 class="fw-bold mb-1">{{ __('Coupon') }}</h4>
                                        <div class="text-muted small">{{ __('Apply an available code before checkout.') }}</div>
                                    </div>
                                    @if($cart['coupon_code'])
                                        <span class="lc-status-badge lc-badge-completed">{{ $cart['coupon_code'] }}</span>
                                    @endif
                                </div>

                                @if($cart['coupon'])
                                    <div class="lc-note-card p-3 mb-3">
                                        <div class="fw-bold mb-1">{{ $cart['coupon_label'] }}</div>
                                        <div class="text-muted small">{{ __('Discount applied successfully to this cart.') }}</div>
                                    </div>
                                    <form method="POST" action="{{ route('cart.coupon.remove') }}" data-submit-loading>
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn lc-btn-soft w-100" type="submit" data-loading-text="{{ __('Removing...') }}">{{ __('Remove coupon') }}</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('cart.coupon.apply') }}" data-submit-loading>
                                        @csrf
                                        <div class="input-group">
                                            <input type="text" name="coupon_code" value="{{ old('coupon_code') }}" class="form-control lc-form-control" placeholder="{{ __('Enter coupon code') }}">
                                            <button class="btn lc-btn-primary" type="submit" data-loading-text="{{ __('Applying...') }}">{{ __('Apply') }}</button>
                                        </div>
                                    </form>
                                @endif
                            </div>

                            @if(($offerSignals ?? collect())->isNotEmpty())
                                <div class="lc-card p-4 cart-offers-card">
                                    <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                                        <div>
                                            <h4 class="fw-bold mb-1">{{ __('Personalized offers') }}</h4>
                                            <div class="text-muted small">{{ __('Use the current basket and recent interest to surface the strongest next offer.') }}</div>
                                        </div>
                                        <span class="lc-badge"><i class="bi bi-magic"></i>{{ __('Smart offers') }}</span>
                                    </div>
                                    <div class="d-grid gap-3">
                                        @foreach($offerSignals as $signal)
                                            <article class="cart-offer-signal">
                                                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                                    <span class="cart-offer-signal__chip">{{ $signal['chip'] }}</span>
                                                    <strong class="small">{{ $signal['emphasis'] }}</strong>
                                                </div>
                                                <div class="fw-bold mb-1">{{ $signal['headline'] }}</div>
                                                <div class="text-muted small">{{ $signal['copy'] }}</div>
                                            </article>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            <div class="lc-card p-4">
                                <div class="d-flex justify-content-between align-items-start gap-2 mb-4">
                                    <div>
                                        <h4 class="fw-bold mb-1">{{ __('Order summary') }}</h4>
                                        <div class="text-muted small">{{ __('A final preview before you continue.') }}</div>
                                    </div>
                                    <span class="lc-badge"><i class="bi bi-bag-check"></i>{{ __('Ready') }}</span>
                                </div>

                                <div class="lc-summary-row"><span class="text-muted">{{ __('Items') }}</span><strong>{{ $cart['items_count'] }}</strong></div>
                                <div class="lc-summary-row"><span class="text-muted">{{ __('Subtotal') }}</span><strong>EGP {{ number_format($cart['subtotal'], 2) }}</strong></div>
                                @if($cart['discount'] > 0)
                                    <div class="lc-summary-row"><span class="text-muted">{{ __('Coupon discount') }}</span><strong class="text-success">- EGP {{ number_format($cart['discount'], 2) }}</strong></div>
                                @endif
                                @if(($cart['promotion_discount'] ?? 0) > 0)
                                    <div class="lc-summary-row"><span class="text-muted">{{ __('Promotion') }}</span><strong class="text-success">- EGP {{ number_format($cart['promotion_discount'], 2) }}</strong></div>
                                @endif
                                <div class="lc-summary-row"><span class="text-muted">{{ __('Shipping') }}</span><strong>EGP {{ number_format($cart['shipping'], 2) }}</strong></div>
                            @if(!empty($shippingGoal) && !$shippingGoal['qualified'])
                                <div class="lc-note-card p-3 mt-3">
                                    <div class="fw-bold mb-1">{{ __('Small basket boost opportunity') }}</div>
                                    <div class="small text-muted">{{ __('You are only :amount away from the shipping target.', ['amount' => 'EGP ' . number_format($shippingGoal['remaining'], 2)]) }}</div>
                                </div>
                            @endif
                                <div class="lc-summary-row"><span class="text-muted">{{ __('Tax') }}</span><strong>EGP {{ number_format($cart['tax'], 2) }}</strong></div>
                                <div class="lc-summary-divider"></div>
                                <div class="lc-summary-row fs-5"><span class="fw-bold">{{ __('Total') }}</span><span class="fw-bold">EGP {{ number_format($cart['total'], 2) }}</span></div>

                                <div class="lc-note-card p-3 mb-3">
                                    <div class="fw-bold mb-1">{{ __('Checkout feeling') }}</div>
                                    <div class="text-muted small">{{ __('Everything looks ready. Continue to checkout to confirm address, payment, and final review.') }}</div>
                                </div>

                                @auth
                                    <a href="{{ route('checkout.index') }}" class="btn lc-btn-primary w-100">{{ __('Proceed to checkout') }}</a>
                                @else
                                    <a href="{{ route('login') }}" class="btn lc-btn-primary w-100">{{ __('Login to continue') }}</a>
                                @endauth
                            </div>
                        </div>
                    </div>
                </div>

                @if(($upsellProducts ?? collect())->isNotEmpty())
                    <section class="mt-4">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
                            <div>
                                <span class="lc-section-kicker">{{ __('Boost the order') }}</span>
                                <h2 class="h4 fw-bold mb-0">{{ __('You may want to add one more item') }}</h2>
                            </div>
                            <span class="lc-badge"><i class="bi bi-graph-up-arrow"></i>{{ __('AOV-ready suggestions') }}</span>
                        </div>
                        <div class="row g-4">
                            @foreach($upsellProducts as $product)
                                <div class="col-md-6 col-xl-4">
                                    @include('frontend.sections.partials.product-card', ['product' => $product])
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif


                @include('frontend.sections.ai-recommendation-strip', [
                    'products' => $aiRecommendedProducts ?? collect(),
                    'subtitle' => __('AI recommendations'),
                    'title' => __('Predicted products to lift this basket'),
                    'description' => __('The engine favors attach-rate opportunities, relevant companions, and commercially strong products for the current cart.'),
                    'insight' => $aiRecommendationInsight ?? null,
                    'badge' => __('Cart prediction engine'),
                ])

                @include('frontend.sections.personalized-product-strip', [
                    'products' => $personalizedProducts ?? collect(),
                    'subtitle' => __('Recommended for you'),
                    'title' => __('Merchandising picks for this basket'),
                    'description' => __('Use cart intent plus recent browsing to highlight the products most likely to increase order value.'),
                    'badge' => __('Smart merchandising'),
                ])

                @include('frontend.sections.personalized-product-strip', [
                    'products' => $recentlyViewedProducts ?? collect(),
                    'subtitle' => __('Recently viewed'),
                    'title' => __('Still thinking about these?'),
                    'description' => __('Bring viewed products back into the decision flow while the customer is already close to checkout.'),
                    'badge' => __('Return path'),
                ])
            @endif
        </div>
    </div>
</section>
@endsection


@push('styles')
<style>
.cart-offers-card{background:linear-gradient(180deg,#ffffff 0%,color-mix(in srgb,var(--lc-soft) 76%, white) 100%)}.cart-offer-signal{padding:.95rem;border-radius:1rem;background:rgba(255,255,255,.84);border:1px solid color-mix(in srgb,var(--lc-border) 80%, white);box-shadow:0 12px 30px color-mix(in srgb,var(--lc-primary) 7%, transparent)}.cart-offer-signal__chip{display:inline-flex;align-items:center;padding:.35rem .6rem;border-radius:999px;background:#eff6ff;border:1px solid #bfdbfe;color:#1d4ed8;font-size:.75rem;font-weight:800}
.cart-aov-progress__bar{height:10px;border-radius:999px;background:color-mix(in srgb,var(--lc-border) 70%, white);overflow:hidden}.cart-aov-progress__bar span{display:block;height:100%;border-radius:inherit;background:linear-gradient(90deg,var(--lc-primary),var(--lc-secondary))}.cart-aov-progress{background:linear-gradient(180deg,#fff 0%,color-mix(in srgb,var(--lc-soft) 72%, white) 100%)}
</style>
@endpush
