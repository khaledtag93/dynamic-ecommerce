<label class="form-label fw-semibold">{{ $label }}</label>
<div class="admin-color-field">
    <input type="color" value="{{ $value }}" class="form-control form-control-color" title="{{ __('Choose a color') }}" data-sync-color="{{ $name }}">
    <input type="text" id="{{ $name }}" name="{{ $name }}" value="{{ $value }}" class="form-control admin-color-code">
</div>
