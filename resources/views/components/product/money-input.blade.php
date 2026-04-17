@props([
    'label' => 'Price',
    'name' => 'price',
    'wireModel' => null,
])

@php
    $model = $wireModel ?? $name;
@endphp

<div class="mb-3">
    <label class="form-label">{{ $label }}</label>
    <input 
        type="number" 
        step="0.01"
        class="form-control @error($model) is-invalid @enderror"
        wire:model.defer="{{ $model }}"
    />
    @error($model) <div class="invalid-feedback">{{ $message }}</div> @enderror 
</div>
