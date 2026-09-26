@props(['errors'])

@if($errors->any())
    @php
        $growthErrorFields = collect($errors->keys())
            ->map(fn ($key) => preg_replace('/\.\d+(?=\.|$)/', '[]', $key))
            ->unique()
            ->values();
    @endphp
    <div class="alert alert-danger mb-4" role="alert" data-growth-validation-summary>
        <div class="fw-bold mb-1">{{ __('Please review the highlighted fields.') }}</div>
        <div class="small mb-2">{{ __('Some information could not be saved. Fix the items below and try again.') }}</div>
        <ul class="mb-0 ps-3">
            @foreach($errors->all() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    </div>

    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const form = document.querySelector('[data-growth-form]');
        if (!form) return;

        const errorFields = @json($growthErrorFields);
        let firstInvalid = null;

        errorFields.forEach((name) => {
            const candidates = Array.from(form.elements).filter((field) => {
                if (!field.name) return false;
                return field.name === name || field.name === name.replace(/\[\]$/, '') || (name.endsWith('[]') && field.name === name);
            });

            candidates.forEach((field) => {
                field.classList.add('is-invalid');
                field.setAttribute('aria-invalid', 'true');
                firstInvalid ??= field;
            });
        });

        if (firstInvalid) {
            firstInvalid.focus({ preventScroll: true });
            firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });
    </script>
    @endpush
@endif
