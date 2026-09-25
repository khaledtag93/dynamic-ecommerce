@extends('layouts.admin')

@section('title', __('New support case') . ' | Admin')

@section('content')
<x-admin.page-header
    :kicker="__('Customer service')"
    :title="__('New support case')"
    :description="__('Create a tracked case for a customer, an order, or an internal follow-up that may later be linked to a customer.')"
>
    <a href="{{ route('admin.support.index') }}" class="btn btn-light border">{{ __('Back to support') }}</a>
</x-admin.page-header>

<div class="admin-page-shell">
    <div class="admin-card">
        <div class="admin-card-body">
            <form method="POST" action="{{ route('admin.support.store') }}" data-submit-loading>
                @csrf
                <div class="row g-3">
                    <div class="col-lg-6">
                        <label class="form-label fw-semibold">{{ __('Customer') }}</label>
                        <select name="customer_id" class="form-select @error('customer_id') is-invalid @enderror">
                            <option value="">{{ __('No customer selected') }}</option>
                            @foreach($customers as $customer)
                                <option value="{{ $customer->id }}" @selected((string)old('customer_id') === (string)$customer->id)>{{ $customer->name }} · {{ $customer->email }}</option>
                            @endforeach
                        </select>
                        @error('customer_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-lg-6">
                        <label class="form-label fw-semibold">{{ __('Order') }}</label>
                        <select name="order_id" class="form-select @error('order_id') is-invalid @enderror">
                            <option value="">{{ __('No order selected') }}</option>
                            @foreach($orders as $order)
                                <option value="{{ $order->id }}" @selected((string)old('order_id') === (string)$order->id)>{{ $order->order_number }} · {{ $order->customer_name ?: $order->customer_email }}</option>
                            @endforeach
                        </select>
                        @error('order_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-lg-8">
                        <label class="form-label fw-semibold">{{ __('Subject') }}</label>
                        <input name="subject" value="{{ old('subject') }}" class="form-control @error('subject') is-invalid @enderror" maxlength="180" required>
                        @error('subject')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-lg-4">
                        <label class="form-label fw-semibold">{{ __('Category') }}</label>
                        <input name="category" value="{{ old('category') }}" class="form-control @error('category') is-invalid @enderror" maxlength="80" placeholder="{{ __('Orders, payment, delivery...') }}">
                        @error('category')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">{{ __('Priority') }}</label>
                        <select name="priority" class="form-select" required>
                            @foreach(\App\Models\SupportCase::priorityOptions() as $value => $label)
                                <option value="{{ $value }}" @selected(old('priority', \App\Models\SupportCase::PRIORITY_NORMAL) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">{{ __('First message visibility') }}</label>
                        <select name="visibility" class="form-select" required>
                            <option value="{{ \App\Models\SupportCaseMessage::VISIBILITY_CUSTOMER }}">{{ __('Customer-visible reply') }}</option>
                            <option value="{{ \App\Models\SupportCaseMessage::VISIBILITY_INTERNAL }}" @selected(old('visibility') === \App\Models\SupportCaseMessage::VISIBILITY_INTERNAL)>{{ __('Internal note') }}</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">{{ __('Message') }}</label>
                        <textarea name="message" rows="7" class="form-control @error('message') is-invalid @enderror" maxlength="5000" required>{{ old('message') }}</textarea>
                        @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <div class="admin-form-actions">
                            <div class="admin-form-actions-copy">
                                <div class="admin-form-actions-title">{{ __('Create case with context') }}</div>
                                <div class="admin-form-actions-subtitle">{{ __('The case keeps its customer, order, ownership, status, priority, and conversation history together.') }}</div>
                            </div>
                            <div class="admin-form-actions-buttons">
                                <a href="{{ route('admin.support.index') }}" class="btn btn-light border">{{ __('Cancel') }}</a>
                                <button class="btn btn-primary" data-loading-text="{{ __('Creating case...') }}">{{ __('Create support case') }}</button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
