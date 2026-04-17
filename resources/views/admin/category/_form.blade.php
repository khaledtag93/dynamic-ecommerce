<div class="row g-4">
    <div class="col-lg-8">
        <div class="card admin-card mb-4">
            <div class="card-header">
                <h5 class="mb-0">{{ __('Basic Information') }}</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">{{ __('Name') }} <span class="required-star">*</span></label>
                        <input type="text" name="name" value="{{ old('name', $category?->name ?? '') }}" class="form-control @error('name') is-invalid @enderror" placeholder="{{ __('Category name') }}">
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">{{ __('Slug') }} <span class="required-star">*</span></label>
                        <input type="text" name="slug" value="{{ old('slug', $category?->slug ?? '') }}" class="form-control @error('slug') is-invalid @enderror" placeholder="category-slug">
                        @error('slug')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12 mb-3">
                        <label class="form-label">{{ __('Description') }}</label>
                        <textarea class="form-control @error('description') is-invalid @enderror" name="description" rows="5" placeholder="{{ __('Write a short description') }}">{{ old('description', $category?->description ?? '') }}</textarea>
                        @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>


        <div class="card admin-card mb-4">
            <div class="card-header">
                <h5 class="mb-0">{{ __('Translations') }}</h5>
            </div>
            <div class="card-body">
                @php($translationLocales = ['en' => __('English'), 'ar' => __('Arabic')])
                <ul class="nav nav-pills mb-3" role="tablist">
                    @foreach($translationLocales as $locale => $label)
                        <li class="nav-item" role="presentation">
                            <button class="nav-link {{ $loop->first ? 'active' : '' }}" data-bs-toggle="pill" data-bs-target="#category-translation-{{ $locale }}" type="button" role="tab">{{ $label }}</button>
                        </li>
                    @endforeach
                </ul>
                <div class="tab-content">
                    @foreach($translationLocales as $locale => $label)
                        @php($currentTranslation = $translations[$locale] ?? [])
                        <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="category-translation-{{ $locale }}" role="tabpanel">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">{{ __('Name') }} ({{ strtoupper($locale) }})</label>
                                    <input type="text" name="translations[{{ $locale }}][name]" value="{{ old("translations.$locale.name", $currentTranslation['name'] ?? '') }}" class="form-control">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">{{ __('Slug') }} ({{ strtoupper($locale) }})</label>
                                    <input type="text" name="translations[{{ $locale }}][slug]" value="{{ old("translations.$locale.slug", $currentTranslation['slug'] ?? '') }}" class="form-control" dir="ltr">
                                </div>
                                <div class="col-12 mb-3">
                                    <label class="form-label">{{ __('Description') }} ({{ strtoupper($locale) }})</label>
                                    <textarea class="form-control" name="translations[{{ $locale }}][description]" rows="3">{{ old("translations.$locale.description", $currentTranslation['description'] ?? '') }}</textarea>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">{{ __('Meta Title') }} ({{ strtoupper($locale) }})</label>
                                    <input type="text" name="translations[{{ $locale }}][meta_title]" value="{{ old("translations.$locale.meta_title", $currentTranslation['meta_title'] ?? '') }}" class="form-control">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">{{ __('Keywords') }} ({{ strtoupper($locale) }})</label>
                                    <input type="text" name="translations[{{ $locale }}][meta_keyword]" value="{{ old("translations.$locale.meta_keyword", $currentTranslation['meta_keyword'] ?? '') }}" class="form-control">
                                </div>
                                <div class="col-12 mb-0">
                                    <label class="form-label">{{ __('Meta Description') }} ({{ strtoupper($locale) }})</label>
                                    <textarea class="form-control" name="translations[{{ $locale }}][meta_description]" rows="3">{{ old("translations.$locale.meta_description", $currentTranslation['meta_description'] ?? '') }}</textarea>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="card admin-card mb-4">
            <div class="card-header">
                <h5 class="mb-0">{{ __('Search Engine Setup') }}</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">{{ __('Meta Title') }} <span class="required-star">*</span></label>
                        <input type="text" name="meta_title" value="{{ old('meta_title', $category?->meta_title ?? '') }}" class="form-control @error('meta_title') is-invalid @enderror">
                        @error('meta_title')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12 mb-3">
                        <label class="form-label">{{ __('Keywords') }} <span class="required-star">*</span></label>
                        <textarea class="form-control @error('meta_keyword') is-invalid @enderror" name="meta_keyword" rows="3">{{ old('meta_keyword', $category?->meta_keyword ?? '') }}</textarea>
                        @error('meta_keyword')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12 mb-0">
                        <label class="form-label">{{ __('Meta Description') }} <span class="required-star">*</span></label>
                        <textarea class="form-control @error('meta_description') is-invalid @enderror" name="meta_description" rows="4">{{ old('meta_description', $category?->meta_description ?? '') }}</textarea>
                        @error('meta_description')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card admin-card mb-4">
            <div class="card-header">
                <h5 class="mb-0">{{ __('Media & Visibility') }}</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">{{ __('Image') }}</label>
                    <input type="file" name="image" id="categoryImageInput" class="form-control @error('image') is-invalid @enderror" accept="image/*">
                    @error('image')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small class="text-muted d-block mt-2">{{ __('Choose a new image to replace the current one instantly in the preview below.') }}</small>
                </div>

              <div class="mb-4">
    <p class="text-muted mb-2">{{ __('Preview') }}</p>

    <img
        src="{{ !empty($category?->image_url)
            ? $category->image_url . '?v=' . optional($category?->updated_at)->timestamp
            : 'https://via.placeholder.com/800x600?text=Category+Image' }}"
        alt="{{ $category?->name ?? __('Category preview') }}"
        id="categoryImagePreview"
        class="admin-thumb-lg"
    >
</div>

                <div class="admin-toggle-card">
                    <div>
                        <div class="fw-semibold mb-1">{{ __('Hide category') }}</div>
                        <small class="text-muted">{{ __('Enable this option to hide the category from the storefront.') }}</small>
                    </div>
                    <div class="form-check form-switch admin-switch-wrap m-0">
                        <input class="form-check-input" type="checkbox" role="switch" id="statusSwitch" name="status" value="1" @checked((int) old('status', $category?->status ?? 0) === 1)>
                        <label class="visually-hidden" for="statusSwitch">{{ __('Hide category') }}</label>
                    </div>
                </div>
            </div>
        </div>

        <div class="admin-form-actions">
            <div class="admin-form-actions-copy">
                <div class="admin-form-actions-title">{{ __('Ready to save?') }}</div>
                <div class="admin-form-actions-subtitle">{{ __('Review the category details, translation content, image, and visibility settings before saving your changes.') }}</div>
            </div>
            <div class="admin-form-actions-buttons">
                <a href="{{ route('admin.categories.index') }}" class="btn btn-light admin-btn-soft admin-back-btn">
                    <i class="mdi mdi-arrow-left"></i>
                    {{ __('Cancel') }}
                </a>
                <button type="submit" class="btn btn-primary">{{ $submitLabel }}</button>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
    .admin-toggle-card {
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:1rem;
        padding:1rem 1.1rem;
        border:1px solid color-mix(in srgb, var(--admin-primary) 18%, white);
        border-radius:1rem;
        background:color-mix(in srgb, var(--admin-surface) 76%, white);
    }
    .admin-switch-wrap .form-check-input {
        float:none;
        margin:0;
        width:3rem;
        height:1.55rem;
    }
    .admin-back-btn {
        border-radius:0.95rem;
        padding-inline:1rem;
    }
</style>
@endpush

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const input = document.getElementById('categoryImageInput');
        const preview = document.getElementById('categoryImagePreview');
        if (!input || !preview) return;

        input.addEventListener('change', function (event) {
            const file = event.target.files && event.target.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = function (e) {
                preview.src = e.target.result;
            };
            reader.readAsDataURL(file);
        });
    });
</script>
@endpush
