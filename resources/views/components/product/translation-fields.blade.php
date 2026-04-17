@props(['locale'])

<div>
  <div class="mb-3">
    <label class="form-label">
      Name ({{ strtoupper($locale) }})
    </label>
    <input type="text" class="form-control @error("translations.$locale.name") is-invalid @enderror"
           wire:model.defer="translations.{{ $locale }}.name">
    @error("translations.$locale.name")<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>

  <div class="mb-3">
    <label class="form-label">
      Slug ({{ strtoupper($locale) }})
    </label>
    <input type="text" class="form-control @error("translations.$locale.slug") is-invalid @enderror"
           wire:model.defer="translations.{{ $locale }}.slug">
    @error("translations.$locale.slug")<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>

  <div class="mb-3">
    <label class="form-label">
      Description ({{ strtoupper($locale) }})
    </label>
    <textarea rows="4" class="form-control @error("translations.$locale.description") is-invalid @enderror"
              wire:model.defer="translations.{{ $locale }}.description"></textarea>
    @error("translations.$locale.description")<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>
</div>
