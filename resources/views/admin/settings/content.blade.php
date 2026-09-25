@extends('layouts.admin')

@section('title', __('Store Content') . ' | Admin')

@section('content')
<x-admin.page-header :kicker="__('Settings')" :title="__('Store Content & Policies')" :description="__('Manage public contact details, legal pages, trust copy, and cancellation rules from one consistent workspace.')" />

<div class="admin-page-shell settings-page">
<div class="admin-card">
    <div class="admin-card-body">
        <form class="admin-form-shell" method="POST" action="{{ route('admin.settings.content.update') }}" data-admin-section-tabs="store-content" data-admin-error-fields="{{ json_encode($errors->keys()) }}">
            @csrf
            @method('PUT')

            <x-admin.section-tabs id="content" :sections="[
                'contact' => __('Contact page'),
                'footer' => __('Storefront footer'),
                'checkout' => __('Checkout trust content'),
                'cancellation' => __('Customer cancellation'),
                'privacy' => __('Privacy Policy'),
                'terms' => __('Terms & Conditions'),
                'refund' => __('Refund Policy'),
                'shipping' => __('Shipping Policy'),
            ]" />

            <div class="row g-4 mb-4 admin-settings-section" id="content-panel-contact" role="tabpanel" aria-labelledby="content-tab-contact" data-admin-section-panel="contact">
                <div class="col-12"><h4 class="mb-0">{{ __('Contact page') }}</h4></div>
                <div class="col-md-6"><label class="form-label fw-semibold">{{ __('Page title') }}</label><input class="form-control" name="contact_page_title" value="{{ old('contact_page_title', $settings['contact_page_title'] ?? '') }}"></div>
                <div class="col-md-6"><label class="form-label fw-semibold">{{ __('Page subtitle') }}</label><input class="form-control" name="contact_page_subtitle" value="{{ old('contact_page_subtitle', $settings['contact_page_subtitle'] ?? '') }}"></div>
                <div class="col-12"><label class="form-label fw-semibold">{{ __('Intro copy') }}</label><textarea class="form-control" rows="4" name="contact_page_intro">{{ old('contact_page_intro', $settings['contact_page_intro'] ?? '') }}</textarea></div>
                <div class="col-md-6"><label class="form-label fw-semibold">{{ __('Support email') }}</label><input class="form-control" name="store_support_email" value="{{ old('store_support_email', $settings['store_support_email'] ?? '') }}"></div>
                <div class="col-md-6"><label class="form-label fw-semibold">{{ __('Support phone') }}</label><input class="form-control" name="store_support_phone" value="{{ old('store_support_phone', $settings['store_support_phone'] ?? '') }}"></div>
                <div class="col-md-6"><label class="form-label fw-semibold">{{ __('WhatsApp') }}</label><input class="form-control" name="store_support_whatsapp" value="{{ old('store_support_whatsapp', $settings['store_support_whatsapp'] ?? '') }}"></div>
                <div class="col-md-6"><label class="form-label fw-semibold">{{ __('Business website') }}</label><input class="form-control" name="store_business_website" value="{{ old('store_business_website', $settings['store_business_website'] ?? '') }}"></div>
                <div class="col-md-6"><label class="form-label fw-semibold">{{ __('Address') }}</label><input class="form-control" name="store_contact_address" value="{{ old('store_contact_address', $settings['store_contact_address'] ?? '') }}"></div>
                <div class="col-md-6"><label class="form-label fw-semibold">{{ __('Map embed URL') }}</label><input class="form-control" name="store_contact_map_url" value="{{ old('store_contact_map_url', $settings['store_contact_map_url'] ?? '') }}"></div>
                <div class="col-md-6"><label class="form-label fw-semibold">{{ __('Business hours (EN)') }}</label><input class="form-control" name="store_contact_hours_en" value="{{ old('store_contact_hours_en', $settings['store_contact_hours_en'] ?? '') }}"></div>
                <div class="col-md-6"><label class="form-label fw-semibold">{{ __('Business hours (AR)') }}</label><input class="form-control" name="store_contact_hours_ar" value="{{ old('store_contact_hours_ar', $settings['store_contact_hours_ar'] ?? '') }}"></div>
                @foreach(['contact_show_email' => __('Show email'),'contact_show_phone' => __('Show phone'),'contact_show_whatsapp' => __('Show WhatsApp'),'contact_show_address' => __('Show address'),'contact_show_hours' => __('Show business hours'),'contact_show_map' => __('Show map')] as $key => $label)
                    <div class="col-md-4">
                        <div class="admin-switch-card h-100 p-3 rounded-4 border d-flex align-items-center justify-content-between gap-3">
                            <label class="fw-bold mb-0" for="{{ $key }}">{{ $label }}</label>
                            <div class="form-check form-switch m-0 flex-nowrap">
                                <input type="checkbox" role="switch" id="{{ $key }}" class="form-check-input" name="{{ $key }}" value="1" @checked(($settings[$key] ?? '0') === '1')>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="row g-4 mb-4 admin-settings-section" id="content-panel-footer" role="tabpanel" aria-labelledby="content-tab-footer" data-admin-section-panel="footer">
                <div class="col-12">
                    <h4 class="mb-1">{{ __('Storefront footer') }}</h4>
                    <p class="text-muted mb-0">{{ __('Choose which footer sections customers see. Empty support details remain hidden automatically.') }}</p>
                </div>
                @foreach([
                    'footer_show_shop' => __('Show shop links'),
                    'footer_show_policies' => __('Show policy links'),
                    'footer_show_categories' => __('Show categories'),
                    'footer_show_support' => __('Show support details'),
                    'footer_show_trust' => __('Show trust highlights'),
                    'footer_show_experience_note' => __('Show experience note'),
                    'footer_show_whatsapp' => __('Show WhatsApp link'),
                    'footer_show_website' => __('Show website link'),
                ] as $key => $label)
                    <div class="col-md-6 col-xl-4">
                        <div class="admin-switch-card h-100 p-3 rounded-4 border d-flex align-items-center justify-content-between gap-3">
                            <label class="fw-bold mb-0" for="{{ $key }}">{{ $label }}</label>
                            <div class="form-check form-switch m-0 flex-nowrap">
                                <input type="checkbox" role="switch" id="{{ $key }}" class="form-check-input" name="{{ $key }}" value="1" @checked(($settings[$key] ?? '1') === '1')>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="row g-4 mb-4 admin-settings-section" id="content-panel-checkout" role="tabpanel" aria-labelledby="content-tab-checkout" data-admin-section-panel="checkout">
                <div class="col-12"><h4 class="mb-0">{{ __('Checkout trust content') }}</h4></div>
                <div class="col-12"><label class="form-label fw-semibold">{{ __('Support note') }}</label><textarea class="form-control" rows="3" name="checkout_support_note">{{ old('checkout_support_note', $settings['checkout_support_note'] ?? '') }}</textarea></div>
                <div class="col-12"><label class="form-label fw-semibold">{{ __('Secure checkout notice') }}</label><textarea class="form-control" rows="3" name="checkout_secure_notice">{{ old('checkout_secure_notice', $settings['checkout_secure_notice'] ?? '') }}</textarea></div>
                <div class="col-md-4"><label class="form-label fw-semibold">{{ __('COD note') }}</label><textarea class="form-control" rows="4" name="checkout_cod_note">{{ old('checkout_cod_note', $settings['checkout_cod_note'] ?? '') }}</textarea></div>
                <div class="col-md-4"><label class="form-label fw-semibold">{{ __('Bank transfer note') }}</label><textarea class="form-control" rows="4" name="checkout_bank_transfer_note">{{ old('checkout_bank_transfer_note', $settings['checkout_bank_transfer_note'] ?? '') }}</textarea></div>
                <div class="col-md-4"><label class="form-label fw-semibold">{{ __('Online payment note') }}</label><textarea class="form-control" rows="4" name="checkout_online_note">{{ old('checkout_online_note', $settings['checkout_online_note'] ?? '') }}</textarea></div>
            </div>

            <div class="row g-4 mb-4 admin-settings-section" id="content-panel-cancellation" role="tabpanel" aria-labelledby="content-tab-cancellation" data-admin-section-panel="cancellation">
                <div class="col-12"><h4 class="mb-0">{{ __('Customer cancellation') }}</h4></div>
                <div class="col-md-4">
                    <div class="admin-switch-card h-100 p-3 rounded-4 border d-flex align-items-center justify-content-between gap-3">
                        <label class="fw-bold mb-0" for="orders_allow_customer_cancellation">{{ __('Allow customer cancellation') }}</label>
                        <div class="form-check form-switch m-0 flex-nowrap">
                            <input type="checkbox" role="switch" id="orders_allow_customer_cancellation" class="form-check-input" name="orders_allow_customer_cancellation" value="1" @checked(($settings['orders_allow_customer_cancellation'] ?? '1') === '1')>
                        </div>
                    </div>
                </div>
                <div class="col-md-8"><label class="form-label fw-semibold">{{ __('Cancellation note') }}</label><textarea class="form-control" rows="3" name="orders_customer_cancellation_note">{{ old('orders_customer_cancellation_note', $settings['orders_customer_cancellation_note'] ?? '') }}</textarea></div>
            </div>

            <div class="admin-inline-note mb-4">
                <i class="mdi mdi-shield-check-outline"></i>
                <span>{{ __('Publish only policy text reviewed for your business and jurisdiction. Blank policy bodies are shown to customers as not yet published instead of using assumed legal terms.') }}</span>
            </div>

            @foreach(['privacy' => __('Privacy Policy'),'terms' => __('Terms & Conditions'),'refund' => __('Refund Policy'),'shipping' => __('Shipping Policy')] as $key => $label)
                <div class="row g-4 mb-4 admin-settings-section" id="content-panel-{{ $key }}" role="tabpanel" aria-labelledby="content-tab-{{ $key }}" data-admin-section-panel="{{ $key }}">
                    <div class="col-12"><h4 class="mb-0">{{ $label }}</h4></div>
                    <div class="col-md-6"><label class="form-label fw-semibold">{{ __('Title') }}</label><input class="form-control" name="legal_{{ $key }}_title" value="{{ old('legal_'.$key.'_title', $settings['legal_'.$key.'_title'] ?? '') }}"></div>
                    <div class="col-12"><label class="form-label fw-semibold">{{ __('Intro') }}</label><textarea class="form-control" rows="3" name="legal_{{ $key }}_intro">{{ old('legal_'.$key.'_intro', $settings['legal_'.$key.'_intro'] ?? '') }}</textarea></div>
                    <div class="col-12"><label class="form-label fw-semibold">{{ __('Body (HTML allowed)') }}</label><textarea class="form-control" rows="8" name="legal_{{ $key }}_body">{{ old('legal_'.$key.'_body', $settings['legal_'.$key.'_body'] ?? '') }}</textarea></div>
                </div>
            @endforeach

            <div class="admin-form-actions mt-4">
                <div class="admin-form-actions-copy">
                    <div class="admin-form-actions-title">{{ __('Ready to save?') }}</div>
                    <div class="admin-form-actions-subtitle">{{ __('Review public content, policy pages, and cancellation rules, then save these storefront content settings when you are ready.') }}</div>
                </div>
                <div class="admin-form-actions-buttons">
                    <button type="submit" class="btn btn-primary">{{ __('Save content settings') }}</button>
                </div>
            </div>
        </form>
    </div>
</div>
</div>
@endsection
