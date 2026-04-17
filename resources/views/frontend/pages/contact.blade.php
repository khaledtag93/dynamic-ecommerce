@extends('layouts.app')

@section('title', ($settings['contact_page_title'] ?? __('Contact us')) . ' | ' . ($storeSettings['store_name'] ?? 'Store'))

@section('content')
<section class="py-5 lc-page-shell">
    <div class="container">
        <div class="mb-4">
            <div class="text-uppercase small text-muted fw-bold">{{ __('Support') }}</div>
            <h1 class="lc-section-title mb-2">{{ $settings['contact_page_title'] ?? __('Contact us') }}</h1>
            <p class="text-muted mb-0">{{ $settings['contact_page_subtitle'] ?? '' }}</p>
        </div>

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="lc-card p-4 h-100">
                    <h4 class="fw-bold mb-3">{{ __('How we can help') }}</h4>
                    <p class="text-muted mb-4">{{ $settings['contact_page_intro'] ?? '' }}</p>
                    <div class="row g-3">
                        @if(($settings['contact_show_email'] ?? '1') === '1' && !empty($settings['store_support_email']))
                            <div class="col-md-6">
                                <div class="border rounded-4 p-3 h-100">
                                    <div class="fw-bold mb-2"><i class="bi bi-envelope me-2"></i>{{ __('Email') }}</div>
                                    <a href="mailto:{{ $settings['store_support_email'] }}">{{ $settings['store_support_email'] }}</a>
                                </div>
                            </div>
                        @endif
                        @if(($settings['contact_show_phone'] ?? '1') === '1' && !empty($settings['store_support_phone']))
                            <div class="col-md-6">
                                <div class="border rounded-4 p-3 h-100">
                                    <div class="fw-bold mb-2"><i class="bi bi-telephone me-2"></i>{{ __('Phone') }}</div>
                                    <a href="tel:{{ preg_replace('/\s+/', '', $settings['store_support_phone']) }}">{{ $settings['store_support_phone'] }}</a>
                                </div>
                            </div>
                        @endif
                        @if(($settings['contact_show_whatsapp'] ?? '1') === '1' && !empty($settings['store_support_whatsapp']))
                            <div class="col-md-6">
                                <div class="border rounded-4 p-3 h-100">
                                    <div class="fw-bold mb-2"><i class="bi bi-whatsapp me-2"></i>{{ __('WhatsApp') }}</div>
                                    <div>{{ $settings['store_support_whatsapp'] }}</div>
                                </div>
                            </div>
                        @endif
                        @if(($settings['contact_show_address'] ?? '1') === '1' && !empty($settings['store_contact_address']))
                            <div class="col-md-6">
                                <div class="border rounded-4 p-3 h-100">
                                    <div class="fw-bold mb-2"><i class="bi bi-geo-alt me-2"></i>{{ __('Address') }}</div>
                                    <div class="text-muted">{{ $settings['store_contact_address'] }}</div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="lc-card p-4 mb-4">
                    <h4 class="fw-bold mb-3">{{ __('Business details') }}</h4>
                    @if(!empty($settings['store_business_website']))
                        <div class="d-flex justify-content-between gap-3 mb-3 flex-wrap">
                            <span class="text-muted">{{ __('Website') }}</span>
                            <a href="{{ $settings['store_business_website'] }}" target="_blank" rel="noopener">{{ $settings['store_business_website'] }}</a>
                        </div>
                    @endif
                    @if(($settings['contact_show_hours'] ?? '1') === '1')
                        <div class="d-flex justify-content-between gap-3 flex-wrap">
                            <span class="text-muted">{{ __('Business hours') }}</span>
                            <strong>{{ app()->getLocale() === 'ar' ? ($settings['store_contact_hours_ar'] ?? '') : ($settings['store_contact_hours_en'] ?? '') }}</strong>
                        </div>
                    @endif
                </div>
                <div class="lc-card p-4">
                    <h4 class="fw-bold mb-3">{{ __('Store policies') }}</h4>
                    <div class="d-flex flex-column gap-2">
                        <a href="{{ route('frontend.privacy') }}">{{ __('Privacy Policy') }}</a>
                        <a href="{{ route('frontend.terms') }}">{{ __('Terms & Conditions') }}</a>
                        <a href="{{ route('frontend.refund') }}">{{ __('Refund Policy') }}</a>
                        <a href="{{ route('frontend.shipping') }}">{{ __('Shipping Policy') }}</a>
                    </div>
                </div>
            </div>
        </div>

        @if(($settings['contact_show_map'] ?? '0') === '1' && !empty($settings['store_contact_map_url']))
            <div class="lc-card p-4 mt-4">
                <h4 class="fw-bold mb-3">{{ __('Map') }}</h4>
                <div class="ratio ratio-21x9 rounded-4 overflow-hidden">
                    <iframe src="{{ $settings['store_contact_map_url'] }}" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                </div>
            </div>
        @endif
    </div>
</section>
@endsection
