@extends('layouts.admin')

@section('title', __('WhatsApp Settings'))

@section('content')
@php
    $messageTypeLabels = [
        'order_confirmation' => __('Order confirmation'),
        'order_status_update' => __('Order status update'),
        'delivery_update' => __('Delivery update'),
        'admin_test' => __('Admin test'),
    ];

    $logStatusLabels = [
        'pending' => __('Pending'),
        'sent' => __('Sent'),
        'failed' => __('Failed'),
        'skipped' => __('Skipped'),
    ];
@endphp
<style>
.whatsapp-page{display:grid;gap:1.25rem}
.whatsapp-page>.admin-card{background:transparent;border:0;box-shadow:none;margin:0!important}
.whatsapp-page>.admin-card>.admin-card-body{padding:0}
.whatsapp-page [id^="wa-"]{scroll-margin-top:1.5rem}
.wa-section-nav{display:flex;gap:.55rem;flex-wrap:wrap}
.wa-section-nav a{display:inline-flex;align-items:center;gap:.45rem;padding:.6rem .85rem;border-radius:999px;background:var(--admin-surface);border:1px solid var(--admin-border);color:var(--admin-text);font-size:.82rem;font-weight:800;text-decoration:none}
.wa-section-nav a:hover{color:var(--admin-primary-dark);border-color:color-mix(in srgb,var(--admin-primary) 30%,var(--admin-border));background:var(--admin-primary-soft)}
.whatsapp-page .wa-section-card{background:var(--admin-surface);border:1px solid var(--admin-border);border-radius:var(--admin-radius-lg);box-shadow:0 14px 32px color-mix(in srgb,var(--admin-text) 6%,transparent)}
.whatsapp-page .wa-switch-card{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.9rem 1rem;border:1px solid var(--admin-border);border-radius:var(--admin-radius-md);background:color-mix(in srgb,var(--admin-surface) 94%,var(--admin-bg) 6%)}
.whatsapp-page .wa-switch-card .wa-switch-copy{min-width:0}
.whatsapp-page .wa-switch-card .wa-switch-title{font-weight:700;color:var(--admin-text);line-height:1.35}
.whatsapp-page .wa-switch-card .form-check{padding:0;min-height:0}
.whatsapp-page .wa-switch-card .form-check-input{float:none;margin:0;width:3rem;height:1.55rem}
.whatsapp-page .wa-metric-card .admin-stat-value{font-size:1.75rem}
@media(max-width:768px){.wa-section-nav a{flex:1 1 calc(50% - .55rem);justify-content:center}.whatsapp-page .wa-switch-card{padding:.8rem .9rem}}
</style>
<div class="container-fluid px-0 whatsapp-page settings-page">
    <x-admin.page-header
        :kicker="__('Phase 4')"
        :title="__('WhatsApp Settings')"
        :description="__('Manage provider identifiers, templates, queue behavior, test sends, and delivery monitoring. Sensitive credentials stay server-managed.')"
        :breadcrumbs="[
            ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
            ['label' => __('Phase 4'), 'url' => route('admin.settings.notifications')],
            ['label' => __('WhatsApp Settings'), 'current' => true],
        ]"
    />

    <nav class="wa-section-nav" aria-label="{{ __('WhatsApp settings sections') }}">
        <a href="#wa-overview"><i class="mdi mdi-view-dashboard-outline"></i>{{ __('Overview') }}</a>
        <a href="#wa-configuration"><i class="mdi mdi-tune-variant"></i>{{ __('Channel & queue') }}</a>
        <a href="#wa-provider"><i class="mdi mdi-cloud-cog-outline"></i>{{ __('Provider & templates') }}</a>
        <a href="#wa-test-tools"><i class="mdi mdi-flask-outline"></i>{{ __('Test tools') }}</a>
        <a href="#wa-logs"><i class="mdi mdi-text-box-search-outline"></i>{{ __('Logs') }}</a>
    </nav>

    <div class="admin-card mb-4">
        <div class="admin-card-body">

            <div class="row g-3 mb-4" id="wa-overview">
                @foreach([
                    ['label' => __('Total logs'), 'value' => $summary['total'] ?? 0, 'class' => 'secondary'],
                    ['label' => __('Sent'), 'value' => $summary['sent'] ?? 0, 'class' => 'success'],
                    ['label' => __('Failed'), 'value' => $summary['failed'] ?? 0, 'class' => 'danger'],
                    ['label' => __('Skipped'), 'value' => $summary['skipped'] ?? 0, 'class' => 'warning'],
                    ['label' => __('Pending'), 'value' => $summary['pending'] ?? 0, 'class' => 'info'],
                ] as $item)
                    <div class="col-lg col-md-4 col-sm-6">
                        <div class="admin-card admin-stat-card wa-metric-card h-100 p-3">
                            <div class="text-muted small mb-1">{{ $item['label'] }}</div>
                            <div class="fs-3 fw-bold text-{{ $item['class'] }}">{{ $item['value'] }}</div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="row g-4" id="wa-configuration">
                <div class="col-xl-8">
                    <form method="POST" action="{{ route('admin.settings.whatsapp.update') }}" data-submit-loading>
                        @csrf
                        @method('PUT')

                        <div class="row g-4 mb-4">
                            <div class="col-xl-6">
                                <div class="wa-section-card p-4 h-100">
                                    <h4 class="admin-section-title mb-0">{{ __('Channel controls & defaults') }}</h4>
                                    <div class="admin-section-subtitle mb-3">{{ __('Choose the default channel behavior, locale fallback, and event coverage for WhatsApp sends.') }}</div>
                                    <div class="wa-switch-card mb-3">
                                        <div class="wa-switch-copy"><label class="wa-switch-title mb-0" for="whatsapp_enabled">{{ __('Enable WhatsApp channel') }}</label></div>
                                        <div class="form-check form-switch admin-switch-wrap m-0">
                                            <input class="form-check-input" type="checkbox" role="switch" id="whatsapp_enabled" name="whatsapp_enabled" value="1" @checked(old('whatsapp_enabled', $storeSettings['whatsapp_enabled'] ?? '0') == '1')>
                                        </div>
                                    </div>
                                    <div class="wa-switch-card mb-3">
                                        <div class="wa-switch-copy"><label class="wa-switch-title mb-0" for="whatsapp_feature_order_confirmation">{{ __('Order created / confirmation') }}</label></div>
                                        <div class="form-check form-switch admin-switch-wrap m-0">
                                            <input class="form-check-input" type="checkbox" role="switch" id="whatsapp_feature_order_confirmation" name="whatsapp_feature_order_confirmation" value="1" @checked(old('whatsapp_feature_order_confirmation', $storeSettings['whatsapp_feature_order_confirmation'] ?? '0') == '1')>
                                        </div>
                                    </div>
                                    <div class="wa-switch-card mb-3">
                                        <div class="wa-switch-copy"><label class="wa-switch-title mb-0" for="whatsapp_feature_order_status_update">{{ __('Order status update') }}</label></div>
                                        <div class="form-check form-switch admin-switch-wrap m-0">
                                            <input class="form-check-input" type="checkbox" role="switch" id="whatsapp_feature_order_status_update" name="whatsapp_feature_order_status_update" value="1" @checked(old('whatsapp_feature_order_status_update', $storeSettings['whatsapp_feature_order_status_update'] ?? '0') == '1')>
                                        </div>
                                    </div>
                                    <div class="wa-switch-card mb-4">
                                        <div class="wa-switch-copy"><label class="wa-switch-title mb-0" for="whatsapp_feature_delivery_update">{{ __('Delivery update') }}</label></div>
                                        <div class="form-check form-switch admin-switch-wrap m-0">
                                            <input class="form-check-input" type="checkbox" role="switch" id="whatsapp_feature_delivery_update" name="whatsapp_feature_delivery_update" value="1" @checked(old('whatsapp_feature_delivery_update', $storeSettings['whatsapp_feature_delivery_update'] ?? '0') == '1')>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">{{ __('Default provider') }}</label>
                                        <input type="text" class="form-control" name="whatsapp_default_provider" value="{{ old('whatsapp_default_provider', $storeSettings['whatsapp_default_provider'] ?? 'meta') }}">
                                    </div>
                                    <div>
                                        <label class="form-label fw-semibold">{{ __('Fallback locale') }}</label>
                                        <select name="whatsapp_fallback_locale" class="form-select">
                                            <option value="ar" @selected(old('whatsapp_fallback_locale', $storeSettings['whatsapp_fallback_locale'] ?? 'ar') === 'ar')>{{ __('Arabic') }}</option>
                                            <option value="en" @selected(old('whatsapp_fallback_locale', $storeSettings['whatsapp_fallback_locale'] ?? 'ar') === 'en')>{{ __('English') }}</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="col-xl-6">
                                <div class="wa-section-card p-4 h-100">
                                    <h4 class="admin-section-title mb-0">{{ __('Queue dispatch & safety guards') }}</h4>
                                    <div class="admin-section-subtitle mb-3">{{ __('Control queue dispatch, retries, duplicate protection, and delivery safety limits.') }}</div>
                                    <div class="wa-switch-card mb-3">
                                        <div class="wa-switch-copy"><label class="wa-switch-title mb-0" for="whatsapp_queue_enabled">{{ __('Use queue dispatch') }}</label></div>
                                        <div class="form-check form-switch admin-switch-wrap m-0">
                                            <input class="form-check-input" type="checkbox" role="switch" id="whatsapp_queue_enabled" name="whatsapp_queue_enabled" value="1" @checked(old('whatsapp_queue_enabled', $storeSettings['whatsapp_queue_enabled'] ?? '0') == '1')>
                                        </div>
                                    </div>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold">{{ __('Queue connection') }}</label>
                                            <input type="text" class="form-control" name="whatsapp_queue_connection" value="{{ old('whatsapp_queue_connection', $storeSettings['whatsapp_queue_connection'] ?? '') }}">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold">{{ __('Queue name') }}</label>
                                            <input type="text" class="form-control" name="whatsapp_queue_queue" value="{{ old('whatsapp_queue_queue', $storeSettings['whatsapp_queue_queue'] ?? 'default') }}">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-semibold">{{ __('Tries') }}</label>
                                            <input type="number" min="1" max="20" class="form-control" name="whatsapp_queue_tries" value="{{ old('whatsapp_queue_tries', $storeSettings['whatsapp_queue_tries'] ?? '3') }}">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-semibold">{{ __('Backoff seconds') }}</label>
                                            <input type="text" class="form-control" name="whatsapp_queue_backoff_seconds" value="{{ old('whatsapp_queue_backoff_seconds', $storeSettings['whatsapp_queue_backoff_seconds'] ?? '60,180,300') }}">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-semibold">{{ __('Job timeout') }}</label>
                                            <input type="number" min="30" max="600" class="form-control" name="whatsapp_queue_timeout" value="{{ old('whatsapp_queue_timeout', $storeSettings['whatsapp_queue_timeout'] ?? '120') }}">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-semibold">{{ __('Duplicate window') }}</label>
                                            <input type="number" min="0" max="1440" class="form-control" name="whatsapp_duplicate_window_minutes" value="{{ old('whatsapp_duplicate_window_minutes', $storeSettings['whatsapp_duplicate_window_minutes'] ?? '30') }}">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-semibold">{{ __('Rate-limit window') }}</label>
                                            <input type="number" min="1" max="1440" class="form-control" name="whatsapp_rate_limit_window_minutes" value="{{ old('whatsapp_rate_limit_window_minutes', $storeSettings['whatsapp_rate_limit_window_minutes'] ?? '15') }}">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-semibold">{{ __('Max attempts') }}</label>
                                            <input type="number" min="1" max="100" class="form-control" name="whatsapp_rate_limit_max_attempts" value="{{ old('whatsapp_rate_limit_max_attempts', $storeSettings['whatsapp_rate_limit_max_attempts'] ?? '5') }}">
                                        </div>
                                    </div>
                                    <div class="small text-muted mt-3">{{ __('Queue needs a live worker in production. Guards prevent duplicates and burst sends to the same customer.') }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="wa-section-card p-4 mb-4" id="wa-provider">
                            <h4 class="mb-1">{{ __('Meta API Credentials') }}</h4>
                            <div class="admin-section-subtitle mb-3">{{ __('Configure non-secret provider identifiers here; sensitive credentials remain server-managed.') }}</div>
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">{{ __('Base URL') }}</label>
                                    <input type="url" class="form-control" name="whatsapp_meta_base_url" value="{{ old('whatsapp_meta_base_url', $storeSettings['whatsapp_meta_base_url'] ?? 'https://graph.facebook.com') }}">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label fw-semibold">{{ __('Graph version') }}</label>
                                    <input type="text" class="form-control" name="whatsapp_meta_graph_version" value="{{ old('whatsapp_meta_graph_version', $storeSettings['whatsapp_meta_graph_version'] ?? 'v23.0') }}">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label fw-semibold">{{ __('Phone number ID') }}</label>
                                    <input type="text" class="form-control" name="whatsapp_meta_phone_number_id" value="{{ old('whatsapp_meta_phone_number_id', $storeSettings['whatsapp_meta_phone_number_id'] ?? '') }}">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label fw-semibold">{{ __('Business account ID') }}</label>
                                    <input type="text" class="form-control" name="whatsapp_meta_business_account_id" value="{{ old('whatsapp_meta_business_account_id', $storeSettings['whatsapp_meta_business_account_id'] ?? '') }}">
                                </div>
                                <div class="col-md-1">
                                    <label class="form-label fw-semibold">{{ __('Timeout') }}</label>
                                    <input type="number" min="5" max="120" class="form-control" name="whatsapp_meta_timeout" value="{{ old('whatsapp_meta_timeout', $storeSettings['whatsapp_meta_timeout'] ?? '20') }}">
                                </div>
                                <div class="col-12">
                                    <div class="rounded-4 border p-3 bg-light-subtle">
                                        <div class="fw-semibold mb-2">{{ __('Server-managed Meta credentials') }}</div>
                                        <div class="d-flex gap-2 flex-wrap">
                                            <span class="badge {{ filled(config('whatsapp.meta.access_token')) ? 'text-bg-success' : 'text-bg-secondary' }}">{{ __('Access token') }}: {{ filled(config('whatsapp.meta.access_token')) ? __('Configured') : __('Missing') }}</span>
                                            <span class="badge {{ filled(config('whatsapp.meta.app_secret')) ? 'text-bg-success' : 'text-bg-secondary' }}">{{ __('App secret') }}: {{ filled(config('whatsapp.meta.app_secret')) ? __('Configured') : __('Missing') }}</span>
                                            <span class="badge {{ filled(config('whatsapp.meta.verify_token')) ? 'text-bg-success' : 'text-bg-secondary' }}">{{ __('Verify token') }}: {{ filled(config('whatsapp.meta.verify_token')) ? __('Configured') : __('Missing') }}</span>
                                        </div>
                                        <div class="admin-helper-text mt-2">{{ __('Sensitive Meta credentials are configured only through the server environment and are never displayed or stored from this page.') }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        @php
                            $templateCards = [
                                'order_confirmation' => __('Order confirmation'),
                                'order_status_update' => __('Order status update'),
                                'delivery_update' => __('Delivery update'),
                            ];
                        @endphp
                        <div class="row g-4 mb-4" id="wa-templates">
                            @foreach($templateCards as $templateKey => $templateLabel)
                                <div class="col-xl-4">
                                    <div class="wa-section-card p-4 h-100">
                                        <h4 class="mb-3">{{ $templateLabel }}</h4>
                                        <div class="row g-3">
                                            <div class="col-12">
                                                <label class="form-label fw-semibold">{{ __('Arabic template name') }}</label>
                                                <input type="text" class="form-control" name="whatsapp_template_{{ $templateKey }}_name_ar" value="{{ old('whatsapp_template_'.$templateKey.'_name_ar', $storeSettings['whatsapp_template_'.$templateKey.'_name_ar'] ?? '') }}">
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label fw-semibold">{{ __('Arabic language code') }}</label>
                                                <input type="text" class="form-control" name="whatsapp_template_{{ $templateKey }}_language_ar" value="{{ old('whatsapp_template_'.$templateKey.'_language_ar', $storeSettings['whatsapp_template_'.$templateKey.'_language_ar'] ?? 'ar') }}">
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label fw-semibold">{{ __('English template name') }}</label>
                                                <input type="text" class="form-control" name="whatsapp_template_{{ $templateKey }}_name_en" value="{{ old('whatsapp_template_'.$templateKey.'_name_en', $storeSettings['whatsapp_template_'.$templateKey.'_name_en'] ?? '') }}">
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label fw-semibold">{{ __('English language code') }}</label>
                                                <input type="text" class="form-control" name="whatsapp_template_{{ $templateKey }}_language_en" value="{{ old('whatsapp_template_'.$templateKey.'_language_en', $storeSettings['whatsapp_template_'.$templateKey.'_language_en'] ?? 'en_US') }}">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="admin-form-actions mt-4">
                            <div class="admin-form-actions-copy">
                                <div class="admin-form-actions-title">{{ __('Ready to save?') }}</div>
                                <div class="admin-form-actions-subtitle">{{ __('Review provider identifiers, approved templates, and safety toggles. Sensitive credentials are managed on the server.') }}</div>
                            </div>
                            <div class="admin-form-actions-buttons">
                                <button type="submit" class="btn btn-primary" data-loading-text="{{ __('Saving WhatsApp changes...') }}">{{ __('Save WhatsApp changes') }}</button>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="col-xl-4" id="wa-test-tools">
                    <div class="wa-section-card p-4 h-100">
                        <h4 class="mb-3">{{ __('Admin test send') }}</h4>
                        <p class="admin-helper-text">{{ __('Send a controlled test using a real order or generated sample data, then review the result instantly in the logs below.') }}</p>
                        <form method="POST" action="{{ route('admin.settings.whatsapp.test-send') }}" data-submit-loading>
                            @csrf
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label fw-semibold">{{ __('Phone') }}</label>
                                    <input type="text" class="form-control" name="phone" placeholder="201288599926">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">{{ __('Locale') }}</label>
                                    <select name="locale" class="form-select">
                                        <option value="ar">AR</option>
                                        <option value="en">EN</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">{{ __('Type') }}</label>
                                    <select name="message_type" class="form-select">
                                        <option value="order_confirmation">{{ __('Order confirmation') }}</option>
                                        <option value="order_status_update">{{ __('Order status update') }}</option>
                                        <option value="delivery_update">{{ __('Delivery update') }}</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold">{{ __('Sample order (optional)') }}</label>
                                    <select name="order_id" class="form-select">
                                        <option value="">{{ __('Use generated sample data') }}</option>
                                        @foreach($recentOrders as $recentOrder)
                                            <option value="{{ $recentOrder->id }}">{{ $recentOrder->order_number }} — {{ $recentOrder->customer_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12">
                                    <div class="admin-form-actions">
                                        <div class="admin-form-actions-copy">
                                            <div class="admin-form-actions-title">{{ __('Ready for a controlled test?') }}</div>
                                            <div class="admin-form-actions-subtitle">{{ __('Send one WhatsApp test using a real order or generated sample data so the result can be reviewed immediately in the logs.') }}</div>
                                        </div>
                                        <div class="admin-form-actions-buttons">
                                            <button type="submit" class="btn btn-outline-primary" data-loading-text="{{ __('Sending WhatsApp test...') }}">{{ __('Send test message now') }}</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="admin-card">
        <div class="admin-card-body">
            <div class="row g-4 mb-4">
                <div class="col-xl-4 col-md-6">
                    <div class="wa-section-card p-4 h-100">
                        <div class="text-muted small mb-1">{{ __('Order confirmation logs') }}</div>
                        <div class="fs-3 fw-bold text-primary">{{ $messageTypeSummary['order_confirmation'] ?? 0 }}</div>
                        <div class="admin-helper-text mt-2">{{ __('Created checkout messages and manual confirmation sends.') }}</div>
                    </div>
                </div>
                <div class="col-xl-4 col-md-6">
                    <div class="wa-section-card p-4 h-100">
                        <div class="text-muted small mb-1">{{ __('Order status update logs') }}</div>
                        <div class="fs-3 fw-bold text-warning">{{ $messageTypeSummary['order_status_update'] ?? 0 }}</div>
                        <div class="admin-helper-text mt-2">{{ __('Confirmed / processing / cancelled lifecycle updates.') }}</div>
                    </div>
                </div>
                <div class="col-xl-4 col-md-6">
                    <div class="wa-section-card p-4 h-100">
                        <div class="text-muted small mb-1">{{ __('Delivery update logs') }}</div>
                        <div class="fs-3 fw-bold text-success">{{ $messageTypeSummary['delivery_update'] ?? 0 }}</div>
                        <div class="admin-helper-text mt-2">{{ __('Shipped, out for delivery, and delivered notifications.') }}</div>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-xl-4">
                    <div class="wa-section-card p-4 h-100">
                        <h4 class="mb-3">{{ __('Manual order event resend') }}</h4>
                        <p class="admin-helper-text">{{ __('Resend a specific order event for a real order without waiting for another status update. This is useful for controlled production checks after template approval.') }}</p>
                        <form method="POST" action="{{ route('admin.settings.whatsapp.order-resend', ['order' => 0]) }}" id="manual-order-event-form" data-submit-loading onsubmit="this.action=this.action.replace('/0','/'+this.querySelector('[name=order_id]').value);">
                            @csrf
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label fw-semibold">{{ __('Order') }}</label>
                                    <select name="order_id" class="form-select" required>
                                        <option value="">{{ __('Select an order') }}</option>
                                        @foreach($recentOrders as $recentOrder)
                                            <option value="{{ $recentOrder->id }}">{{ $recentOrder->order_number }} — {{ $recentOrder->customer_name }} — {{ $recentOrder->customer_phone }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold">{{ __('Message type') }}</label>
                                    <select name="message_type" class="form-select" required>
                                        <option value="order_confirmation">{{ __('Order confirmation') }}</option>
                                        <option value="order_status_update">{{ __('Order status update') }}</option>
                                        <option value="delivery_update">{{ __('Delivery update') }}</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <div class="admin-form-actions">
                                        <div class="admin-form-actions-copy">
                                            <div class="admin-form-actions-title">{{ __('Ready to resend?') }}</div>
                                            <div class="admin-form-actions-subtitle">{{ __('Trigger a single controlled resend for the selected order event without waiting for a new customer action.') }}</div>
                                        </div>
                                        <div class="admin-form-actions-buttons">
                                            <button type="submit" class="btn btn-light border" data-loading-text="{{ __('Resending selected event...') }}">{{ __('Resend selected event now') }}</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="col-xl-8" id="wa-logs">
                    <div class="wa-section-card p-4 h-100">
                        <div class="d-flex justify-content-between gap-3 flex-wrap align-items-start mb-4">
                            <div>
                                <h3 class="mb-1">{{ __('WhatsApp logs') }}</h3>
                                <p class="text-muted mb-0">{{ __('Review delivery attempts, provider replies, retries, duplicate skips, admin tests, and detailed payload data from one organized table.') }}</p>
                            </div>
                        </div>

                        <form method="GET" class="row g-3 mb-4" data-submit-loading>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">{{ __('Search') }}</label>
                                <input type="text" class="form-control" name="search" value="{{ request('search') }}" placeholder="{{ __('Order number, customer, phone, template...') }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">{{ __('Status') }}</label>
                                <select name="status" class="form-select">
                                    <option value="">{{ __('All statuses') }}</option>
                                    @foreach(['sent' => __('Sent'),'failed' => __('Failed'),'skipped' => __('Skipped'),'pending' => __('Pending')] as $value => $label)
                                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">{{ __('Message type') }}</label>
                                <select name="message_type" class="form-select">
                                    <option value="">{{ __('All messages') }}</option>
                                    <option value="order_confirmation" @selected(request('message_type') === 'order_confirmation')>{{ __('Order confirmation') }}</option>
                                    <option value="order_status_update" @selected(request('message_type') === 'order_status_update')>{{ __('Order status update') }}</option>
                                    <option value="delivery_update" @selected(request('message_type') === 'delivery_update')>{{ __('Delivery update') }}</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn-outline-primary w-100" data-loading-text="{{ __('Refreshing WhatsApp logs...') }}">{{ __('Apply log filters') }}</button>
                            </div>
                        </form>

                        <div class="table-responsive admin-table-wrap">
                            <table class="table align-middle">
                                <thead>
                                    <tr>
                                        <th>{{ __('Order / Template') }}</th>
                                        <th>{{ __('Customer') }}</th>
                                        <th>{{ __('Type / Locale') }}</th>
                                        <th>{{ __('Status') }}</th>
                                        <th>{{ __('Attempts') }}</th>
                                        <th>{{ __('Provider') }}</th>
                                        <th>{{ __('Created') }}</th>
                                        <th class="text-end">{{ __('Quick actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($logs as $log)
                                        @php
                                            $statusClass = match($log->status) {
                                                'sent' => 'success',
                                                'failed' => 'danger',
                                                'skipped' => 'warning',
                                                default => 'secondary',
                                            };
                                            $meta = $log->meta ?? [];
                                            $collapseId = 'whatsapp-log-'.$log->id;
                                        @endphp
                                        <tr>
                                            <td>
                                                <div class="fw-semibold">{{ $log->order?->order_number ?? ($meta['order_number'] ?? __('Generated sample')) }}</div>
                                                <div class="admin-helper-text">{{ $log->template_name ?: __('No template') }}</div>
                                                @if(!empty($meta['sample_preview']))
                                                    <div class="admin-helper-text mt-1">{{ \Illuminate\Support\Str::limit($meta['sample_preview'], 90) }}</div>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="fw-semibold">{{ $log->order?->customer_name ?? __('Unknown') }}</div>
                                                <div class="admin-helper-text">{{ $log->phone ?: '—' }}</div>
                                            </td>
                                            <td>
                                                <div>{{ $messageTypeLabels[$log->message_type] ?? ucfirst(str_replace('_', ' ', $log->message_type)) }}</div>
                                                <div class="admin-helper-text">{{ strtoupper($log->locale ?? '—') }}</div>
                                                @if(!empty($meta['is_test']))
                                                    <span class="badge text-bg-info mt-1">{{ __('Test send') }}</span>
                                                @endif
                                                @if(!empty($meta['is_retry']))
                                                    <span class="badge text-bg-secondary mt-1">{{ __('Retry attempt') }}</span>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge text-bg-{{ $statusClass }}">{{ $logStatusLabels[$log->status] ?? ucfirst($log->status) }}</span>
                                                @if($log->error_message)
                                                    <div class="admin-helper-text mt-1" style="max-width: 280px;">{{ \Illuminate\Support\Str::limit($log->error_message, 120) }}</div>
                                                @endif
                                            </td>
                                            <td>
                                                <div>{{ $log->attempts }}</div>
                                                @if(!empty($meta['retry_of_log_id']))
                                                    <div class="admin-helper-text">{{ __('Retry from log #:id', ['id' => $meta['retry_of_log_id']]) }}</div>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="small">{{ $log->provider_message_id ?: '—' }}</div>
                                                <div class="admin-helper-text">{{ $meta['provider_status_code'] ?? '—' }}</div>
                                            </td>
                                            <td>
                                                <div>{{ optional($log->sent_at ?? $log->failed_at ?? $log->created_at)->format('Y-m-d H:i') ?: '—' }}</div>
                                                <div class="admin-helper-text">{{ $log->created_at?->diffForHumans() }}</div>
                                            </td>
                                            <td class="text-end">
                                                <div class="d-flex flex-column gap-2 align-items-end">
                                                    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $collapseId }}" aria-expanded="false" title="{{ __('Expand this row to review debug details, payload context, and retry history.') }}">{{ __('Open details') }}</button>
                                                    @if(in_array($log->status, ['failed', 'skipped'], true))
                                                        <form method="POST" action="{{ route('admin.settings.whatsapp.retry', $log) }}" class="d-inline-block" data-submit-loading data-confirm-title="{{ __('Retry WhatsApp send') }}" data-confirm-message="{{ __('Retry this WhatsApp send now?') }}" data-confirm-subtitle="{{ __('A new attempt will be created only if production safety guards do not detect an active duplicate.') }}" data-confirm-ok="{{ __('Retry send') }}" data-confirm-cancel="{{ __('Keep reviewing') }}">
                                                            @csrf
                                                            <button type="submit" class="btn btn-sm btn-outline-primary" data-loading-text="{{ __('Retrying send...') }}" title="{{ __('Retry this WhatsApp log immediately.') }}">{{ __('Retry send') }}</button>
                                                        </form>
                                                    @endif
                                                    @if($log->order_id)
                                                        <form method="POST" action="{{ route('admin.settings.whatsapp.order-resend', $log->order_id) }}" class="d-inline-block" data-submit-loading data-confirm-title="{{ __('Resend this order event') }}" data-confirm-message="{{ __('Resend this order event now?') }}" data-confirm-subtitle="{{ __('A new WhatsApp notification attempt will be created for the selected order event.') }}" data-confirm-ok="{{ __('Resend event now') }}" data-confirm-cancel="{{ __('Keep reviewing') }}">
                                                            @csrf
                                                            <input type="hidden" name="message_type" value="{{ $log->message_type }}">
                                                            <button type="submit" class="btn btn-sm btn-outline-dark" data-loading-text="{{ __('Resending event...') }}" title="{{ __('Create a fresh resend attempt for this order event.') }}">{{ __('Resend event') }}</button>
                                                        </form>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                        <tr class="collapse" id="{{ $collapseId }}">
                                            <td colspan="8" class="bg-light-subtle">
                                                <div class="row g-3 p-2">
                                                    <div class="col-xl-4">
                                                        <div class="rounded-4 border bg-white p-3 h-100">
                                                            <h6 class="admin-section-title mb-3">{{ __('Log summary') }}</h6>
                                                            <div class="small mb-2"><strong>{{ __('Provider') }}:</strong> {{ $log->provider ?: '—' }}</div>
                                                            <div class="small mb-2"><strong>{{ __('Normalized phone') }}:</strong> {{ $log->normalized_phone ?: '—' }}</div>
                                                            <div class="small mb-2"><strong>{{ __('Template language') }}:</strong> {{ $meta['template_language'] ?? '—' }}</div>
                                                            <div class="small mb-2"><strong>{{ __('Order status') }}:</strong> {{ $meta['order_status'] ?? ($log->order?->status ?? '—') }}</div>
                                                            <div class="small mb-2"><strong>{{ __('Delivery status') }}:</strong> {{ $meta['delivery_status'] ?? ($log->order?->delivery_status ?? '—') }}</div>
                                                            <div class="small mb-2"><strong>{{ __('Queue') }}:</strong> {{ !empty($meta['queue_enabled']) ? __('Enabled') : __('Disabled') }}</div>
                                                            <div class="small mb-2"><strong>{{ __('Queue connection') }}:</strong> {{ $meta['queue_connection'] ?? '—' }}</div>
                                                            <div class="small mb-2"><strong>{{ __('Queue name') }}:</strong> {{ $meta['queue_name'] ?? '—' }}</div>
                                                            @if($log->error_message)
                                                                <div class="alert alert-warning admin-flash admin-flash--warning py-2 px-3 mt-3 mb-0">
                                                                    <div class="admin-flash-icon"><i class="mdi mdi-alert-outline"></i></div>
                                                                    <div class="admin-flash-content">
                                                                        <div class="admin-flash-title">{{ __('Provider feedback') }}</div>
                                                                        <div class="admin-flash-subtitle small">{{ $log->error_message }}</div>
                                                                    </div>
                                                                </div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                    <div class="col-xl-4">
                                                        <div class="rounded-4 border bg-white p-3 h-100">
                                                            <h6 class="admin-section-title mb-3">{{ __('Meta / debug details') }}</h6>
                                                            <pre class="small mb-0" style="white-space: pre-wrap; word-break: break-word; max-height: 320px; overflow:auto;">{{ json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                                        </div>
                                                    </div>
                                                    <div class="col-xl-4">
                                                        <div class="rounded-4 border bg-white p-3 h-100">
                                                            <h6 class="admin-section-title mb-3">{{ __('Request / response') }}</h6>
                                                            <div class="small fw-semibold mb-2">{{ __('Request payload') }}</div>
                                                            <pre class="small" style="white-space: pre-wrap; word-break: break-word; max-height: 150px; overflow:auto;">{{ json_encode($log->request_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                                            <div class="small fw-semibold mb-2 mt-3">{{ __('Response payload') }}</div>
                                                            <pre class="small mb-0" style="white-space: pre-wrap; word-break: break-word; max-height: 150px; overflow:auto;">{{ json_encode($log->response_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="text-center py-5"><div class="admin-empty-title">{{ __('No WhatsApp logs yet') }}</div><div class="admin-empty-subtitle">{{ __('Delivery attempts, retries, and provider responses will appear here as soon as activity starts.') }}</div></td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-3">
                            {{ $logs->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
