@extends('layouts.app')

@section('title', __('Checkout') . ' | ' . ($storeSettings['store_name'] ?? 'Storefront'))

@section('content')
<section class="py-5 lc-page-shell checkout-page-shell">
    <div class="container">
        <div class="lc-checkout-shell">
            <div class="lc-checkout-toolbar checkout-hero-toolbar">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
                    <div>
                        <div class="text-uppercase small text-muted fw-bold">{{ __('Checkout') }}</div>
                        <h1 class="lc-section-title mb-2">{{ __('Place your order') }}</h1>
                        <p class="text-muted mb-0">{{ $storeSettings['checkout_support_note'] ?? __('Complete your order using accurate customer, shipping, and payment details.') }}</p>
                    </div>
                    <span class="lc-badge"><i class="bi bi-shield-lock"></i>{{ __('Protected checkout flow') }}</span>
                </div>

                <div class="lc-progress-strip">
                    <div class="lc-progress-step is-complete">
                        <span class="lc-progress-step__dot">1</span>
                        <div>
                            <div class="lc-progress-step__title">{{ __('Cart review') }}</div>
                            <div class="lc-progress-step__copy">{{ __('Products and totals are ready.') }}</div>
                        </div>
                    </div>
                    <div class="lc-progress-step is-active lc-state-pulse">
                        <span class="lc-progress-step__dot">2</span>
                        <div>
                            <div class="lc-progress-step__title">{{ __('Checkout details') }}</div>
                            <div class="lc-progress-step__copy">{{ __('Address, payment, and final confirmation.') }}</div>
                        </div>
                    </div>
                    <div class="lc-progress-step">
                        <span class="lc-progress-step__dot">3</span>
                        <div>
                            <div class="lc-progress-step__title">{{ __('Order placed') }}</div>
                            <div class="lc-progress-step__copy">{{ __('Success page and next steps.') }}</div>
                        </div>
                    </div>
                </div>

                <div class="checkout-trust-row mt-4">
                    <span><i class="bi bi-lock"></i>{{ __('Secure checkout') }}</span>
                    <span><i class="bi bi-truck"></i>{{ __('Delivery details first') }}</span>
                    <span><i class="bi bi-credit-card"></i>{{ __('Trusted payment methods') }}</span>
                    <span><i class="bi bi-headset"></i>{{ __('Support-ready order flow') }}</span>
                </div>
            </div>

            @include('frontend.partials.behavioral-offers', ['cards' => $behavioralOffers['cards'] ?? collect()])

            @if ($errors->has('cart'))
                <div class="alert alert-danger mb-0">{{ $errors->first('cart') }}</div>
            @endif

            <form method="POST" action="{{ route('checkout.store') }}" data-submit-loading>
                @csrf
                @if(!empty($shippingGoal))
                    <div class="lc-card p-3 p-lg-4 mb-4 checkout-aov-progress">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                            <strong>{{ $shippingGoal['qualified'] ? __('Shipping goal reached') : __('One more push before you place the order') }}</strong>
                            <span class="lc-badge"><i class="bi bi-truck"></i>{{ __('Goal') }}: EGP {{ number_format($shippingGoal['goal'], 2) }}</span>
                        </div>
                        <div class="text-muted small mb-3">{{ $shippingGoal['qualified'] ? __('This basket already passed the target, which adds reassurance before payment.') : __('Add :amount more to cross the shipping target before placing the order.', ['amount' => 'EGP ' . number_format($shippingGoal['remaining'], 2)]) }}</div>
                        <div class="checkout-aov-progress__bar"><span style="width: {{ $shippingGoal['progress'] }}%"></span></div>
                    </div>
                @endif

                <div class="row g-4 align-items-start">
                    <div class="col-lg-8">
                        <div class="lc-card p-4 mb-4 checkout-step-card">
                            <div class="checkout-section-head mb-3">
                                <div>
                                    <div class="checkout-step-index">01</div>
                                    <h4 class="fw-bold mb-1">{{ __('Customer details') }}</h4>
                                    <div class="text-muted small">{{ __('We use this information for order confirmation and delivery communication.') }}</div>
                                </div>
                                <span class="lc-badge"><i class="bi bi-person-check"></i>{{ __('Required details') }}</span>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <input name="customer_name" value="{{ old('customer_name', auth()->user()->name) }}" class="form-control lc-form-control @error('customer_name') is-invalid @enderror" placeholder="{{ __('Full name') }}" required>
                                    @error('customer_name') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-6">
                                    <input type="email" name="customer_email" value="{{ old('customer_email', auth()->user()->email) }}" class="form-control lc-form-control @error('customer_email') is-invalid @enderror" placeholder="{{ __('Email address') }}" required>
                                    @error('customer_email') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-6">
                                    <input name="customer_phone" value="{{ old('customer_phone') }}" class="form-control lc-form-control @error('customer_phone') is-invalid @enderror" placeholder="{{ __('Phone number') }}" required>
                                    @error('customer_phone') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-6">
                                    <select name="delivery_method" class="form-select lc-form-select @error('delivery_method') is-invalid @enderror" required>
                                        @foreach($deliveryOptions as $value => $label)
                                            <option value="{{ $value }}" @selected(old('delivery_method', \App\Models\Order::DELIVERY_METHOD_STANDARD) === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('delivery_method') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                </div>
                            </div>
                        </div>

                        <div class="lc-card p-4 mb-4 checkout-step-card">
                            <div class="checkout-section-head mb-3">
                                <div>
                                    <div class="checkout-step-index">02</div>
                                    <h4 class="fw-bold mb-1">{{ __('Payment method') }}</h4>
                                    <div class="text-muted small">{{ __('Choose the payment flow that best matches your customer and business process.') }}</div>
                                </div>
                                <span class="lc-badge"><i class="bi bi-shield-check"></i>{{ __('Secure checkout') }}</span>
                            </div>

                            <div class="payment-icons-row mb-3">
                                <span>{{ __('Visa') }}</span>
                                <span>{{ __('Mastercard') }}</span>
                                <span>{{ __('Cash') }}</span>
                                <span>{{ __('Protected') }}</span>
                            </div>

                            <div class="row g-3">
                                @foreach($paymentOptions as $value => $option)
                                    @php
                                        $note = match($value) {
                                            \App\Models\Order::PAYMENT_METHOD_COD => $storeSettings['checkout_cod_note'] ?? $option['description'],
                                            \App\Models\Order::PAYMENT_METHOD_BANK_TRANSFER => $storeSettings['checkout_bank_transfer_note'] ?? $option['description'],
                                            default => $storeSettings['checkout_online_note'] ?? $option['description'],
                                        };
                                    @endphp
                                    <div class="col-md-6">
                                        <label class="lc-payment-option" data-payment-card>
                                            <input class="form-check-input" type="radio" name="payment_method" value="{{ $value }}" @checked(old('payment_method', array_key_first($paymentOptions)) === $value)>
                                            <div class="lc-payment-option__card">
                                                <div class="lc-payment-option__header">
                                                    <div class="fw-bold">{{ $option['label'] }}</div>
                                                    <span class="lc-payment-option__badge">{{ __('Available') }}</span>
                                                </div>
                                                <div class="text-muted small">{{ $note }}</div>
                                            </div>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                            @error('payment_method') <div class="invalid-feedback d-block mt-2">{{ $message }}</div> @enderror
                        </div>

                        <div class="lc-card p-4 mb-4 checkout-step-card">
                            <div class="checkout-section-head mb-3">
                                <div>
                                    <div class="checkout-step-index">03</div>
                                    <h4 class="fw-bold mb-1">{{ __('Shipping address') }}</h4>
                                    <div class="text-muted small">{{ __('Make sure the shipping destination is complete and easy for the courier to confirm.') }}</div>
                                </div>
                                <span class="lc-badge"><i class="bi bi-geo-alt"></i>{{ __('Delivery ready') }}</span>
                            </div>
                            <div class="row g-3">
                                <div class="col-12">
                                    <input name="shipping_address_line_1" value="{{ old('shipping_address_line_1') }}" class="form-control lc-form-control @error('shipping_address_line_1') is-invalid @enderror" placeholder="{{ __('Address line 1') }}" required>
                                    @error('shipping_address_line_1') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-12">
                                    <input name="shipping_address_line_2" value="{{ old('shipping_address_line_2') }}" class="form-control lc-form-control @error('shipping_address_line_2') is-invalid @enderror" placeholder="{{ __('Address line 2 (optional)') }}">
                                    @error('shipping_address_line_2') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-4">
                                    <input name="shipping_city" value="{{ old('shipping_city') }}" class="form-control lc-form-control @error('shipping_city') is-invalid @enderror" placeholder="{{ __('City') }}" required>
                                    @error('shipping_city') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-4">
                                    <input name="shipping_state" value="{{ old('shipping_state') }}" class="form-control lc-form-control @error('shipping_state') is-invalid @enderror" placeholder="{{ __('State / Area') }}">
                                    @error('shipping_state') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-4">
                                    <input name="shipping_postal_code" value="{{ old('shipping_postal_code') }}" class="form-control lc-form-control @error('shipping_postal_code') is-invalid @enderror" placeholder="{{ __('Postal code') }}">
                                    @error('shipping_postal_code') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-6">
                                    <input name="shipping_country" value="{{ old('shipping_country', 'Egypt') }}" class="form-control lc-form-control @error('shipping_country') is-invalid @enderror" placeholder="{{ __('Country') }}" required>
                                    @error('shipping_country') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                </div>
                            </div>
                        </div>

                        <div class="lc-card p-4 checkout-step-card">
                            @php($billingSame = old('billing_same_as_shipping', '1') === '1')
                            <div class="checkout-section-head mb-3">
                                <div>
                                    <div class="checkout-step-index">04</div>
                                    <h4 class="fw-bold mb-1">{{ __('Billing & notes') }}</h4>
                                    <div class="text-muted small">{{ __('Keep billing aligned with shipping unless the order requires a separate invoice destination.') }}</div>
                                </div>
                            </div>

                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" role="switch" id="billingSame" name="billing_same_as_shipping" value="1" @checked($billingSame) onchange="document.getElementById('billingFields').classList.toggle('d-none', this.checked)">
                                <label class="form-check-label" for="billingSame">{{ __('Billing address is the same as shipping') }}</label>
                            </div>

                            <div id="billingFields" class="{{ $billingSame ? 'd-none' : '' }}">
                                <h4 class="fw-bold mb-3">{{ __('Billing address') }}</h4>
                                <div class="row g-3">
                                    <div class="col-12">
                                        <input name="billing_address_line_1" value="{{ old('billing_address_line_1') }}" class="form-control lc-form-control @error('billing_address_line_1') is-invalid @enderror" placeholder="{{ __('Address line 1') }}">
                                        @error('billing_address_line_1') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="col-12">
                                        <input name="billing_address_line_2" value="{{ old('billing_address_line_2') }}" class="form-control lc-form-control @error('billing_address_line_2') is-invalid @enderror" placeholder="{{ __('Address line 2') }}">
                                        @error('billing_address_line_2') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="col-md-4">
                                        <input name="billing_city" value="{{ old('billing_city') }}" class="form-control lc-form-control @error('billing_city') is-invalid @enderror" placeholder="{{ __('City') }}">
                                        @error('billing_city') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="col-md-4">
                                        <input name="billing_state" value="{{ old('billing_state') }}" class="form-control lc-form-control @error('billing_state') is-invalid @enderror" placeholder="{{ __('State / Area') }}">
                                        @error('billing_state') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="col-md-4">
                                        <input name="billing_postal_code" value="{{ old('billing_postal_code') }}" class="form-control lc-form-control @error('billing_postal_code') is-invalid @enderror" placeholder="{{ __('Postal code') }}">
                                        @error('billing_postal_code') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="col-md-6">
                                        <input name="billing_country" value="{{ old('billing_country', 'Egypt') }}" class="form-control lc-form-control @error('billing_country') is-invalid @enderror" placeholder="{{ __('Country') }}">
                                        @error('billing_country') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="mt-3">
                                <textarea name="notes" rows="4" class="form-control lc-form-control @error('notes') is-invalid @enderror" placeholder="{{ __('Order notes (optional)') }}">{{ old('notes') }}</textarea>
                                @error('notes') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="lc-card p-4 lc-summary-card-sticky checkout-summary-card">
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-4">
                                <div>
                                    <h4 class="fw-bold mb-1">{{ __('Order summary') }}</h4>
                                    <div class="text-muted small">{{ __('Final preview before you place the order.') }}</div>
                                </div>
                                <span class="lc-badge"><i class="bi bi-bag-heart"></i>{{ __('Review') }}</span>
                            </div>

                            <div class="checkout-summary-products d-grid gap-3 mb-3">
                                @foreach($cart['items'] as $item)
                                    @php($productSlug = $item->meta['product_slug'] ?? optional($item->product)->slug)
                                    <div class="checkout-summary-product">
                                        <div class="checkout-summary-product__media">
                                            <img src="{{ $item->image_url ?: 'https://via.placeholder.com/88x88?text=No+Image' }}" alt="{{ $item->product_name }}">
                                        </div>
                                        <div class="checkout-summary-product__body">
                                            @if($productSlug)
                                                <a href="{{ route('frontend.products.show', $productSlug) }}" class="checkout-summary-product__title">{{ $item->product_name }}</a>
                                            @else
                                                <div class="checkout-summary-product__title">{{ $item->product_name }}</div>
                                            @endif
                                            <div class="text-muted small">{{ __('Qty') }}: {{ $item->quantity }}{{ $item->variant_name ? ' • ' . $item->variant_name : '' }}</div>
                                            <div class="checkout-summary-product__price">EGP {{ number_format($item->line_total, 2) }}</div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <div class="lc-summary-row"><span class="text-muted">{{ __('Subtotal') }}</span><strong>EGP {{ number_format($cart['subtotal'], 2) }}</strong></div>
                            @if($cart['coupon'])
                                <div class="lc-summary-row"><span class="text-muted">{{ __('Coupon') }} ({{ $cart['coupon_code'] }})</span><strong class="text-success">- EGP {{ number_format($cart['coupon_discount'], 2) }}</strong></div>
                            @endif
                            @if(($cart['promotion_discount'] ?? 0) > 0)
                                <div class="lc-summary-row"><span class="text-muted">{{ __('Promotion') }}{{ !empty($cart['promotion_label']) ? ' (' . $cart['promotion_label'] . ')' : '' }}</span><strong class="text-success">- EGP {{ number_format($cart['promotion_discount'], 2) }}</strong></div>
                            @endif
                            <div class="lc-summary-row"><span class="text-muted">{{ __('Shipping') }}</span><strong>EGP {{ number_format($cart['shipping'], 2) }}</strong></div>
                            <div class="lc-summary-row"><span class="text-muted">{{ __('Tax') }}</span><strong>EGP {{ number_format($cart['tax'], 2) }}</strong></div>
                            <div class="lc-summary-divider"></div>
                            <div class="lc-summary-row fs-5"><span class="fw-bold">{{ __('Total') }}</span><span class="fw-bold">EGP {{ number_format($cart['total'], 2) }}</span></div>

                            @if(($upsellProducts ?? collect())->isNotEmpty())
                                <div class="checkout-upsell-stack mb-3">
                                    <div class="small text-uppercase fw-bold text-muted mb-2">{{ __('Last-minute boost') }}</div>
                                    @foreach($upsellProducts as $upsellProduct)
                                        <div class="checkout-upsell-item">
                                            <div class="d-flex align-items-center gap-3">
                                                <img src="{{ $upsellProduct->main_image_url ?: 'https://via.placeholder.com/72x72?text=No+Image' }}" alt="{{ $upsellProduct->name }}">
                                                <div>
                                                    <div class="fw-bold">{{ $upsellProduct->name }}</div>
                                                    <div class="small text-muted">{{ __('Quick add-on from the same conversion flow') }}</div>
                                                </div>
                                            </div>
                                            <div class="text-end">
                                                <div class="fw-bold">EGP {{ number_format((float) ($upsellProduct->current_price ?? 0), 2) }}</div>
                                                <a href="{{ route('frontend.products.show', $upsellProduct->slug) }}" class="small fw-semibold">{{ __('View') }}</a>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            @if(($offerSignals ?? collect())->isNotEmpty())
                                <div class="checkout-personalized-offers mb-3">
                                    <div class="small text-uppercase fw-bold text-muted mb-2">{{ __('Personalized offers') }}</div>
                                    @foreach($offerSignals as $signal)
                                        <article class="checkout-personalized-offer-card">
                                            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                                <span class="checkout-personalized-offer-card__chip">{{ $signal['chip'] }}</span>
                                                <strong class="small">{{ $signal['emphasis'] }}</strong>
                                            </div>
                                            <div class="fw-bold mb-1">{{ $signal['headline'] }}</div>
                                            <div class="small text-muted">{{ $signal['copy'] }}</div>
                                        </article>
                                    @endforeach
                                </div>
                            @endif

                            <div class="checkout-confirmation-stack my-3">
                                <div class="lc-note-card p-3">
                                    <div class="fw-bold mb-1">{{ __('Need confidence before placing the order?') }}</div>
                                    <div class="small text-muted">{{ $storeSettings['checkout_secure_notice'] ?? __('Review your summary, choose the right payment method, and place the order when everything looks correct.') }}</div>
                                </div>
                                <div class="checkout-assurance-list">
                                    <div><i class="bi bi-lock"></i>{{ __('Encrypted checkout messaging') }}</div>
                                    <div><i class="bi bi-credit-card"></i>{{ __('Clear payment choice') }}</div>
                                    <div><i class="bi bi-geo-alt"></i>{{ __('Shipping reviewed before payment') }}</div>
                                </div>
                            </div>

                            <button type="submit" class="btn lc-btn-primary w-100" data-loading-text="{{ __('Placing order...') }}">{{ __('Place order') }}</button>
                            <div class="checkout-legal-note mt-3">{{ __('By placing the order, you confirm the entered information and continue under the store terms, shipping, and refund policies.') }}</div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @include('frontend.sections.ai-recommendation-strip', [
        'products' => $aiRecommendedProducts ?? collect(),
        'subtitle' => __('AI recommendations'),
        'title' => __('Predicted final adds before payment'),
        'description' => __('Narrow, high-fit recommendations designed for checkout where relevance matters more than variety.'),
        'insight' => $aiRecommendationInsight ?? null,
        'badge' => __('Checkout prediction engine'),
    ])

    @include('frontend.sections.personalized-product-strip', [
        'products' => $personalizedProducts ?? collect(),
        'subtitle' => __('Recommended for you'),
        'title' => __('Keep momentum with one final set of smart picks'),
        'description' => __('Show relevant products next to checkout so the basket can grow without interrupting the purchase flow.'),
        'badge' => __('Checkout merchandising'),
    ])

    @include('frontend.sections.personalized-product-strip', [
        'products' => $recentlyViewedProducts ?? collect(),
        'subtitle' => __('Recently viewed'),
        'title' => __('Last look before placing the order'),
        'description' => __('Bring back high-intent products while the shopper is making the final decision.'),
        'badge' => __('Session memory'),
    ])
</section>
@endsection

@push('styles')
<style>
.checkout-hero-toolbar{overflow:hidden}
.checkout-trust-row,.payment-icons-row{display:flex;flex-wrap:wrap;gap:.75rem}.checkout-trust-row span,.payment-icons-row span{display:inline-flex;align-items:center;gap:.45rem;padding:.7rem .9rem;border-radius:999px;background:rgba(255,255,255,.8);border:1px solid color-mix(in srgb,var(--lc-border) 80%, white);font-weight:700;color:var(--lc-primary-dark)}
.checkout-step-card{position:relative;overflow:hidden}.checkout-step-card::before{content:"";position:absolute;inset-inline-start:0;top:0;bottom:0;width:4px;background:linear-gradient(180deg,var(--lc-primary),var(--lc-secondary));opacity:.85}.checkout-section-head{display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap}.checkout-step-index{font-size:.78rem;font-weight:800;letter-spacing:.12em;color:var(--lc-primary-dark);margin-bottom:.5rem}
.checkout-summary-card{background:linear-gradient(180deg,#ffffff 0%, color-mix(in srgb,var(--lc-soft) 70%, white) 100%)}
.checkout-summary-product{display:grid;grid-template-columns:72px 1fr;gap:.9rem;padding:.9rem;border-radius:1rem;background:rgba(255,255,255,.84);border:1px solid color-mix(in srgb,var(--lc-border) 80%, white)}.checkout-summary-product__media img{width:72px;height:72px;object-fit:cover;border-radius:.95rem}.checkout-summary-product__title{font-weight:800;color:var(--lc-text);display:block;margin-bottom:.2rem}.checkout-summary-product__price{font-weight:800;margin-top:.35rem}
.checkout-assurance-list{display:grid;gap:.7rem}.checkout-assurance-list div{display:flex;align-items:center;gap:.6rem;padding:.8rem .9rem;border-radius:1rem;background:rgba(255,255,255,.82);border:1px solid color-mix(in srgb,var(--lc-border) 80%, white);font-weight:700;color:var(--lc-text)}.checkout-assurance-list i{color:var(--lc-primary-dark)}
.checkout-legal-note{font-size:.88rem;color:var(--lc-muted);text-align:center}.checkout-personalized-offers{display:grid;gap:.75rem}.checkout-personalized-offer-card{padding:.9rem;border-radius:1rem;background:rgba(255,255,255,.84);border:1px solid color-mix(in srgb,var(--lc-border) 80%, white)}.checkout-personalized-offer-card__chip{display:inline-flex;align-items:center;padding:.34rem .6rem;border-radius:999px;background:#eff6ff;border:1px solid #bfdbfe;color:#1d4ed8;font-size:.75rem;font-weight:800}.checkout-aov-progress__bar{height:10px;border-radius:999px;background:color-mix(in srgb,var(--lc-border) 70%, white);overflow:hidden}.checkout-aov-progress__bar span{display:block;height:100%;border-radius:inherit;background:linear-gradient(90deg,var(--lc-primary),var(--lc-secondary))}.checkout-aov-progress{background:linear-gradient(180deg,#fff 0%,color-mix(in srgb,var(--lc-soft) 72%, white) 100%)}.checkout-upsell-stack{display:grid;gap:.75rem}.checkout-upsell-item{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.9rem;border-radius:1rem;background:rgba(255,255,255,.82);border:1px solid color-mix(in srgb,var(--lc-border) 80%, white)}.checkout-upsell-item img{width:64px;height:64px;object-fit:cover;border-radius:.9rem}
@media (max-width: 767.98px){.checkout-summary-product{grid-template-columns:64px 1fr}.checkout-summary-product__media img{width:64px;height:64px}}
</style>
@endpush
