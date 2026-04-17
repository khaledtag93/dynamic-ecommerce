@extends('layouts.admin')

@section('title', ($mode === 'edit' ? __('Edit Promotion') : __('Create Promotion')) . ' | Admin')

@section('content')
<div class="admin-page-header">
    <div>
        <div class="admin-kicker">{{ __('Sales') }}</div>
        <h1 class="admin-page-title">{{ $mode === 'edit' ? __('Edit Promotion') : __('Create Promotion') }}</h1>
        <p class="admin-page-description">{{ __('Set up automatic discount behavior without changing checkout code later.') }}</p>
    </div>
    <div class="admin-page-actions">
        <a href="{{ route('admin.promotions.index') }}" class="btn btn-light border btn-text-icon"><i class="mdi mdi-arrow-left"></i><span>{{ __('Back to promotions') }}</span></a>
    </div>
</div>

<div class="admin-card">
    <div class="admin-card-body">
        <form method="POST" action="{{ $mode === 'edit' ? route('admin.promotions.update', $promotion) : route('admin.promotions.store') }}" class="row g-3" data-submit-loading>
            @csrf
            @if($mode === 'edit') @method('PUT') @endif

            <div class="col-md-6">
                <label class="form-label">{{ __('Name') }}</label>
                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $promotion->name) }}" required>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label">{{ __('Type') }}</label>
                <select name="type" class="form-select @error('type') is-invalid @enderror">
                    @foreach([
                        'order_percentage' => __('Order percentage'),
                        'order_fixed' => __('Order fixed'),
                        'category_percentage' => __('Category percentage'),
                        'buy_x_get_y' => __('Buy X Get Y'),
                    ] as $value => $label)
                        <option value="{{ $value }}" @selected(old('type', $promotion->type) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">{{ __('Discount value') }}</label>
                <input type="number" step="0.01" name="discount_value" class="form-control @error('discount_value') is-invalid @enderror" value="{{ old('discount_value', $promotion->discount_value ?? 0) }}">
                @error('discount_value')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">{{ __('Category') }}</label>
                <select name="category_id" class="form-select @error('category_id') is-invalid @enderror"><option value="">{{ __('All categories') }}</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((string) old('category_id', $promotion->category_id) === (string) $category->id)>{{ $category->name }}</option>@endforeach</select>
                @error('category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                @if($categories->isEmpty())
                    <div class="section-note">{{ __('No categories available yet. The promotion can still apply at order level.') }}</div>
                @endif
            </div>
            <div class="col-md-4">
                <label class="form-label">{{ __('Min subtotal') }}</label>
                <input type="number" step="0.01" name="min_subtotal" class="form-control @error('min_subtotal') is-invalid @enderror" value="{{ old('min_subtotal', $promotion->min_subtotal) }}">
                @error('min_subtotal')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3"><label class="form-label">{{ __('Buy qty') }}</label><input type="number" name="buy_quantity" class="form-control @error('buy_quantity') is-invalid @enderror" value="{{ old('buy_quantity', $promotion->buy_quantity) }}">@error('buy_quantity')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-3"><label class="form-label">{{ __('Get qty') }}</label><input type="number" name="get_quantity" class="form-control @error('get_quantity') is-invalid @enderror" value="{{ old('get_quantity', $promotion->get_quantity) }}">@error('get_quantity')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-3"><label class="form-label">{{ __('Priority') }}</label><input type="number" name="priority" class="form-control @error('priority') is-invalid @enderror" value="{{ old('priority', $promotion->priority ?? 0) }}">@error('priority')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-3"><label class="form-label">{{ __('Active') }}</label><select name="is_active" class="form-select @error('is_active') is-invalid @enderror"><option value="1" @selected((string) old('is_active', (int) ($promotion->is_active ?? true)) === '1')>{{ __('Yes') }}</option><option value="0" @selected((string) old('is_active', (int) ($promotion->is_active ?? true)) === '0')>{{ __('No') }}</option></select>@error('is_active')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-6"><label class="form-label">{{ __('Starts at') }}</label><input type="datetime-local" name="starts_at" class="form-control @error('starts_at') is-invalid @enderror" value="{{ old('starts_at', optional($promotion->starts_at)->format('Y-m-d\TH:i')) }}">@error('starts_at')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-6"><label class="form-label">{{ __('Ends at') }}</label><input type="datetime-local" name="ends_at" class="form-control @error('ends_at') is-invalid @enderror" value="{{ old('ends_at', optional($promotion->ends_at)->format('Y-m-d\TH:i')) }}">@error('ends_at')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-12">
                <div class="admin-form-actions">
                    <div class="admin-form-actions-copy">
                        <div class="admin-form-actions-title">{{ __('Ready to save?') }}</div>
                        <div class="admin-form-actions-subtitle">{{ __('Review the promotion type, discount values, schedule, and activation status before saving this rule.') }}</div>
                    </div>
                    <div class="admin-form-actions-buttons">
                        <a href="{{ route('admin.promotions.index') }}" class="btn btn-light border">{{ __('Cancel') }}</a>
                        <button class="btn btn-primary btn-text-icon" data-loading-text="{{ __('Saving...') }}"><i class="mdi mdi-content-save-outline"></i><span>{{ $mode === 'edit' ? __('Update promotion') : __('Save promotion') }}</span></button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
