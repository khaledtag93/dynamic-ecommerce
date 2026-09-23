@props(['id', 'sections'])

<nav class="admin-section-nav" aria-label="{{ __('Page sections') }}">
    <div class="admin-section-tabs" role="tablist" aria-label="{{ __('Page sections') }}">
        @foreach($sections as $key => $label)
            <button type="button" class="admin-section-tab" id="{{ $id }}-tab-{{ $key }}"
                role="tab" aria-controls="{{ $id }}-panel-{{ $key }}"
                aria-selected="{{ $loop->first ? 'true' : 'false' }}" tabindex="{{ $loop->first ? '0' : '-1' }}"
                data-admin-section-tab="{{ $key }}">{{ $label }}</button>
        @endforeach
    </div>
</nav>
