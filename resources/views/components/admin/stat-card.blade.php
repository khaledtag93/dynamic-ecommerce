@props([
    'label',
    'value',
    'icon' => null,
    'help' => null,
    'tone' => 'default',
])

<article {{ $attributes->class([
    'admin-card',
    'admin-stat-card',
    'admin-stat-card--v2',
    'admin-stat-card--no-icon' => empty($icon),
    'admin-stat-card--' . $tone => $tone !== 'default',
]) }}>
    @if($icon)
        <span class="admin-stat-icon" aria-hidden="true"><i class="mdi {{ $icon }}"></i></span>
    @endif

    <div class="admin-stat-label">{{ $label }}</div>
    <div class="admin-stat-value">{{ $value }}</div>

    @if(trim($slot) !== '')
        <div class="admin-stat-meta">{{ $slot }}</div>
    @endif

    @if($help)
        <div class="admin-stat-help">{{ $help }}</div>
    @endif
</article>
