@extends('layouts.app')

@section('title', __('New support request') . ' | ' . ($storeSettings['store_name'] ?? 'Storefront'))

@section('content')
<section class="py-5 lc-page-shell">
    <div class="container">
        <x-frontend.page-hero :eyebrow="__('Help & Support')" :title="__('New support request')" :description="__('Tell us what you need help with. Linking an order helps the team understand the context faster.')" class="mb-4" />
        @include('frontend.account.partials.navigation')

        <div class="lc-card p-4">
            <form method="POST" action="{{ route('support.store') }}" class="d-grid gap-3" data-submit-loading>
                @csrf
                <div>
                    <label class="form-label fw-bold">{{ __('Order (optional)') }}</label>
                    <select name="order_id" class="form-select lc-form-control @error('order_id') is-invalid @enderror">
                        <option value="">{{ __('Not related to an order') }}</option>
                        @foreach($orders as $order)
                            <option value="{{ $order->id }}" @selected((string)old('order_id') === (string)$order->id)>{{ $order->order_number }} · {{ $order->status_label }}</option>
                        @endforeach
                    </select>
                    @error('order_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="form-label fw-bold">{{ __('Subject') }}</label>
                    <input name="subject" value="{{ old('subject') }}" class="form-control lc-form-control @error('subject') is-invalid @enderror" maxlength="180" required>
                    @error('subject')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="form-label fw-bold">{{ __('Category (optional)') }}</label>
                    <input name="category" value="{{ old('category') }}" class="form-control lc-form-control @error('category') is-invalid @enderror" maxlength="80" placeholder="{{ __('Order, payment, delivery, return...') }}">
                    @error('category')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="form-label fw-bold">{{ __('How can we help?') }}</label>
                    <textarea name="message" rows="7" class="form-control lc-form-control @error('message') is-invalid @enderror" maxlength="5000" required>{{ old('message') }}</textarea>
                    @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="d-flex gap-2 justify-content-end">
                    <a href="{{ route('support.index') }}" class="btn lc-btn-soft">{{ __('Cancel') }}</a>
                    <button class="btn lc-btn-primary" data-loading-text="{{ __('Sending request...') }}">{{ __('Create support request') }}</button>
                </div>
            </form>
        </div>
    </div>
</section>
@endsection
