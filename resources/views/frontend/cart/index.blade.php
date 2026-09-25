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
                        <p class="text-muted mb-0">{{ __('Review your products, quantities, coupon, and total before checkout.') }}</p>
                    </div>
                    <span class="lc-badge"><i class="bi bi-bag-check"></i>{{ __('Ready for checkout') }}</span>
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
                            {{ $shippingGoal['qualified'] ? __('Great. This order already qualifies for the shipping goal.') : __('Add :amount more to reach the shipping goal.', ['amount' => 'EGP ' . number_format($shippingGoal['remaining'], 2)]) }}
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
                    <p class="text-muted mb-4">{{ __('Start shopping and add the products you need to your cart.') }}</p>
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
                                                <img src="{{ $item->image_url ?: asset('images/storefront-placeholder.svg') }}" alt="{{ $item->product_name }}">
                                            </a>
                                        @else
                                            <div class="lc-cart-item__media">
                                                <img src="{{ $item->image_url ?: asset('images/storefront-placeholder.svg') }}" alt="{{ $item->product_name }}">
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
                                                <span>{{ __('Change quantity and the cart will refresh automatically.') }}</span>
                                            </div>

                                            <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
                                                <form method="POST" action="{{ route('cart.update', $item) }}" class="d-flex align-items-center gap-2 cart-qty-auto-form" data-submit-loading>
                                                    @csrf
                                                    @method('PATCH')
                                                    <div class="lc-qty-shell">
                                                        <span class="small text-muted fw-bold">{{ __('Qty') }}</span>
                                                        <input type="number" name="quantity" min="1" value="{{ $item->quantity }}" class="form-control lc-form-control text-center">
                                                    </div>
                                                    <button type="submit" class="btn lc-btn-soft cart-qty-update-fallback" data-loading-text="{{ __('Updating...') }}">{{ __('Updating...') }}</button>
                                                </form>

                                                <div class="lc-cart-item__actions d-flex align-items-center gap-2 ms-auto">
                                                    <div class="text-end">
                                                        <div class="small text-muted">{{ __('Line total') }}</div>
                                                        <div class="fw-bold fs-5">EGP {{ number_format($item->line_total, 2) }}</div>
                                                    </div>
                                                    <form method="POST" action="{{ route('cart.destroy', $item) }}" data-submit-loading
                                                          data-confirm-title="{{ __('Remove item') }}"
                                                          data-confirm-message="{{ __('Remove this item from your cart?') }}"
                                                          data-confirm-subtitle="{{ __('You can add the product again later if you change your mind.') }}"
                                                          data-confirm-ok="{{ __('Remove item') }}"
                                                          data-confirm-cancel="{{ __('Keep item') }}">
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
                                            <h4 class="fw-bold mb-1">{{ __('Available offers') }}</h4>
                                            <div class="text-muted small">{{ __('Offers and products available for this cart.') }}</div>
                                        </div>
                                        <span class="lc-badge"><i class="bi bi-magic"></i>{{ __('Suggested offers') }}</span>
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
                                        <div class="text-muted small">{{ __('Check subtotal, discounts, shipping, and final total.') }}</div>
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
                                <div class="lc-summary-row"><span class="text-muted">{{ __('Shipping') }}</span><strong>{{ __('Calculated at checkout') }}</strong></div>
                            @if(!empty($shippingGoal) && !$shippingGoal['qualified'])
                                <div class="lc-note-card p-3 mt-3">
                                    <div class="fw-bold mb-1">{{ __('Shipping goal') }}</div>
                                    <div class="small text-muted">{{ __('You are only :amount away from the shipping target.', ['amount' => 'EGP ' . number_format($shippingGoal['remaining'], 2)]) }}</div>
                                </div>
                            @endif
                                <div class="lc-summary-row"><span class="text-muted">{{ __('Tax') }}</span><strong>EGP {{ number_format($cart['tax'], 2) }}</strong></div>
                                <div class="lc-summary-divider"></div>
                                <div class="lc-summary-row fs-5"><span class="fw-bold">{{ __('Total before shipping') }}</span><span class="fw-bold">EGP {{ number_format($cart['total'], 2) }}</span></div>

                                <div class="lc-note-card p-3 mb-3">
                                    <div class="fw-bold mb-1">{{ __('Before checkout') }}</div>
                                    <div class="text-muted small">{{ __('Continue to checkout to confirm your address and payment method.') }}</div>
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
                                <span class="lc-section-kicker">{{ __('Suggested products') }}</span>
                                <h2 class="h4 fw-bold mb-0">{{ __('You may also like') }}</h2>
                            </div>
                            <span class="lc-badge"><i class="bi bi-graph-up-arrow"></i>{{ __('More products') }}</span>
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
                    'subtitle' => __('Recommended'),
                    'title' => __('Products you may also need'),
                    'description' => __('Useful products that can go well with your current cart.'),
                    'insight' => $aiRecommendationInsight ?? null,
                    'badge' => __('Recommended'),
                ])

                @include('frontend.sections.personalized-product-strip', [
                    'products' => $personalizedProducts ?? collect(),
                    'subtitle' => __('Recommended for you'),
                    'title' => __('More products to consider'),
                    'description' => __('A simple selection of products you may want to add before checkout.'),
                    'badge' => __('Suggested'),
                ])

                @include('frontend.sections.personalized-product-strip', [
                    'products' => $recentlyViewedProducts ?? collect(),
                    'subtitle' => __('Recently viewed'),
                    'title' => __('Still thinking about these?'),
                    'description' => __('Products you opened recently are saved here for easy access.'),
                    'badge' => __('Recently viewed'),
                ])
            @endif
        </div>
    </div>
</section>
@endsection


@push('styles')
<style>
.cart-offers-card{background:linear-gradient(180deg,var(--lc-surface) 0%,color-mix(in srgb,var(--lc-soft) 76%, white) 100%)}.cart-offer-signal{padding:.95rem;border-radius:1rem;background:color-mix(in srgb,var(--lc-surface) 92%,transparent);border:1px solid color-mix(in srgb,var(--lc-border) 80%, white);box-shadow:0 12px 30px color-mix(in srgb,var(--lc-primary) 7%, transparent)}.cart-offer-signal__chip{display:inline-flex;align-items:center;padding:.35rem .6rem;border-radius:999px;background:color-mix(in srgb,var(--lc-soft) 82%,var(--lc-surface));border:1px solid color-mix(in srgb,var(--lc-primary) 18%,var(--lc-border));color:var(--lc-primary-dark);font-size:.75rem;font-weight:800}
.cart-aov-progress__bar{height:10px;border-radius:999px;background:color-mix(in srgb,var(--lc-border) 70%, white);overflow:hidden}.cart-aov-progress__bar span{display:block;height:100%;border-radius:inherit;background:linear-gradient(90deg,var(--lc-primary),var(--lc-secondary))}.cart-aov-progress{background:linear-gradient(180deg,var(--lc-surface) 0%,color-mix(in srgb,var(--lc-soft) 72%, white) 100%)}
.cart-qty-update-fallback{display:none}.cart-remove-btn{font-weight:900}
</style>
@endpush


@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.cart-qty-auto-form input[name="quantity"]').forEach(function (input) {
        let timer = null;
        input.addEventListener('change', function () {
            clearTimeout(timer);
            timer = setTimeout(function () {
                if (Number(input.value) < 1) input.value = 1;
                input.closest('form').requestSubmit();
            }, 250);
        });
    });
});
</script>
@endpush
