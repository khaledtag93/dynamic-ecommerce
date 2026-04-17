@extends('layouts.admin')

@section('title', __('Payment Settings') . ' | Admin')

@section('content')
<x-admin.page-header :kicker="__('Settings')" :title="__('Payment Settings')" :description="__('Enable methods safely and prepare gateway configuration without breaking checkout flow.')" />

<div class="admin-page-shell settings-page">
<div class="admin-card">
    <div class="admin-card-body">
        <form class="admin-form-shell" method="POST" action="{{ route('admin.settings.payments.update') }}">
            @csrf
            @method('PUT')

            
            <div class="row g-4 mb-4 admin-settings-section">
                <div class="col-12">
                    <div class="rounded-4 border p-4 bg-light-subtle">
                        <div class="d-flex justify-content-between gap-3 flex-wrap align-items-start mb-3">
                            <div>
                                <h4 class="mb-1">{{ __('Production readiness') }}</h4>
                                <p class="text-muted mb-0">{{ __('Use the exact HTTPS callback URL below inside Paymob dashboard and keep the website legal pages/contact page publicly accessible before requesting review.') }}</p>
                            </div>
                            <span class="badge text-bg-success">{{ __('Paymob review prep') }}</span>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">{{ __('Transaction Processed Callback URL') }}</label>
                                <input type="text" class="form-control" value="{{ route('payments.paymob.callback') }}" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">{{ __('Transaction Response Callback URL') }}</label>
                                <input type="text" class="form-control" value="{{ route('payments.paymob.callback') }}" readonly>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4 admin-settings-section">
                <div class="col-md-4">
                    <label class="form-check admin-switch-card h-100 d-block p-3 rounded-4 border">
                        <input type="checkbox" class="form-check-input me-2" name="payment_cod_enabled" value="1" @checked(($storeSettings['payment_cod_enabled'] ?? '1') === '1')>
                        <span class="fw-bold">{{ __('Cash on Delivery') }}</span>
                        <div class="text-muted small mt-2">{{ __('Keep COD available for manual doorstep collection.') }}</div>
                    </label>
                </div>
                <div class="col-md-4">
                    <label class="form-check admin-switch-card h-100 d-block p-3 rounded-4 border">
                        <input type="checkbox" class="form-check-input me-2" name="payment_bank_transfer_enabled" value="1" @checked(($storeSettings['payment_bank_transfer_enabled'] ?? '1') === '1')>
                        <span class="fw-bold">{{ __('Bank Transfer') }}</span>
                        <div class="text-muted small mt-2">{{ __('Allow manual transfer flow with localized instructions.') }}</div>
                    </label>
                </div>
                <div class="col-md-4">
                    <label class="form-check admin-switch-card h-100 d-block p-3 rounded-4 border">
                        <input type="checkbox" class="form-check-input me-2" name="payment_online_enabled" value="1" @checked(($storeSettings['payment_online_enabled'] ?? '1') === '1')>
                        <span class="fw-bold">{{ __('Online Payment') }}</span>
                        <div class="text-muted small mt-2">{{ __('Foundation-only mode until the provider is connected.') }}</div>
                    </label>
                </div>
            </div>

            <div class="row g-4 mb-4 admin-settings-section">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">{{ __('Gateway provider') }}</label>
                    <input type="text" class="form-control" name="payment_gateway_provider" value="{{ old('payment_gateway_provider', $storeSettings['payment_gateway_provider'] ?? 'paymob') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">{{ __('Gateway mode') }}</label>
                    <select name="payment_gateway_mode" class="form-select">
                        <option value="sandbox" @selected(($storeSettings['payment_gateway_mode'] ?? 'sandbox') === 'sandbox')>{{ __('Sandbox') }}</option>
                        <option value="live" @selected(($storeSettings['payment_gateway_mode'] ?? 'sandbox') === 'live')>{{ __('Live') }}</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">{{ __('Public key') }}</label>
                    <input type="text" class="form-control" name="payment_gateway_public_key" value="{{ old('payment_gateway_public_key', $storeSettings['payment_gateway_public_key'] ?? '') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">{{ __('Secret key') }}</label>
                    <input type="text" class="form-control" name="payment_gateway_secret_key" value="{{ old('payment_gateway_secret_key', $storeSettings['payment_gateway_secret_key'] ?? '') }}">
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">{{ __('Webhook secret') }}</label>
                    <input type="text" class="form-control" name="payment_gateway_webhook_secret" value="{{ old('payment_gateway_webhook_secret', $storeSettings['payment_gateway_webhook_secret'] ?? '') }}">
                </div>
            </div>


            <div class="row g-4 mb-4 admin-settings-section">
                <div class="col-12">
                    <div class="rounded-4 border p-4 bg-light-subtle">
                        <div class="d-flex justify-content-between gap-3 flex-wrap align-items-start mb-3">
                            <div>
                                <h4 class="mb-1">{{ __('Paymob configuration') }}</h4>
                                <p class="text-muted mb-0">{{ __('Recommended for Egypt. Fill these fields to activate real online checkout without changing the storefront flow.') }}</p>
                            </div>
                            <span class="badge text-bg-warning">{{ __('Keep live keys private') }}</span>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">{{ __('Paymob API key') }}</label>
                                <input type="text" class="form-control" name="paymob_api_key" value="{{ old('paymob_api_key', $storeSettings['paymob_api_key'] ?? '') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">{{ __('Paymob integration ID') }}</label>
                                <input type="text" class="form-control" name="paymob_integration_id" value="{{ old('paymob_integration_id', $storeSettings['paymob_integration_id'] ?? '') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">{{ __('Paymob iframe ID') }}</label>
                                <input type="text" class="form-control" name="paymob_iframe_id" value="{{ old('paymob_iframe_id', $storeSettings['paymob_iframe_id'] ?? '') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">{{ __('Paymob HMAC secret') }}</label>
                                <input type="text" class="form-control" name="paymob_hmac_secret" value="{{ old('paymob_hmac_secret', $storeSettings['paymob_hmac_secret'] ?? '') }}">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4 admin-settings-section">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">{{ __('Bank transfer instructions (EN)') }}</label>
                    <textarea name="bank_transfer_instructions_en" rows="6" class="form-control">{{ old('bank_transfer_instructions_en', $storeSettings['bank_transfer_instructions_en'] ?? 'Transfer to your business bank account, then send the transfer reference to support.') }}</textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">{{ __('Bank transfer instructions (AR)') }}</label>
                    <textarea name="bank_transfer_instructions_ar" rows="6" class="form-control">{{ old('bank_transfer_instructions_ar', $storeSettings['bank_transfer_instructions_ar'] ?? 'حوّل المبلغ على الحساب البنكي الخاص بالمتجر ثم احتفظ برقم التحويل للتأكيد مع الدعم.') }}</textarea>
                </div>
            </div>

            <div class="admin-form-actions mt-4">
                <div class="admin-form-actions-copy">
                    <div class="admin-form-actions-title">{{ __('Ready to save?') }}</div>
                    <div class="admin-form-actions-subtitle">{{ __('Review enabled methods, gateway details, and Paymob configuration, then save the payment workspace when you are ready.') }}</div>
                </div>
                <div class="admin-form-actions-buttons">
                    <button type="submit" class="btn btn-primary">{{ __('Save payment settings') }}</button>
                </div>
            </div>
        </form>
    </div>
</div>
</div>
@endsection
