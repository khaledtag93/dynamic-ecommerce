@props([
    'kicker' => null,
    'title',
    'description' => null,
    'breadcrumbs' => [],
])

<div {{ $attributes->class(['admin-page-header']) }}>
    <div>
        @if(!empty($breadcrumbs))
            <nav class="admin-breadcrumbs" aria-label="{{ __('Breadcrumb') }}">
                <ol class="admin-breadcrumb-list">
                    @foreach($breadcrumbs as $breadcrumb)
                        @php
                            $label = is_array($breadcrumb) ? ($breadcrumb['label'] ?? null) : $breadcrumb;
                            $url = is_array($breadcrumb) ? ($breadcrumb['url'] ?? null) : null;
                            $isCurrent = (bool) (is_array($breadcrumb) ? ($breadcrumb['current'] ?? false) : false);
                        @endphp

                        @if($label)
                            <li class="admin-breadcrumb-item {{ $isCurrent ? 'is-current' : '' }}">
                                @if($url && !$isCurrent)
                                    <a href="{{ $url }}">{{ $label }}</a>
                                @else
                                    <span>{{ $label }}</span>
                                @endif
                            </li>
                        @endif
                    @endforeach
                </ol>
            </nav>
        @endif

        @if($kicker)
            <div class="admin-kicker">{{ $kicker }}</div>
        @endif

        <h1 class="admin-page-title">{{ $title }}</h1>

        @if($description)
            <p class="admin-page-description">{{ $description }}</p>
        @endif
    </div>

    @if(trim($slot) !== '')
        <div class="admin-page-actions">{{ $slot }}</div>
    @endif
</div>
