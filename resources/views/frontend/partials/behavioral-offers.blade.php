@php($cards = collect($cards ?? []))
@if($cards->isNotEmpty())
    <div class="behavioral-offers-grid">
        @foreach($cards as $card)
            <article class="behavioral-offer-card behavioral-offer-card--{{ $card['tone'] ?? 'primary' }}">
                <div class="behavioral-offer-card__icon"><i class="bi {{ $card['icon'] ?? 'bi-stars' }}"></i></div>
                <div>
                    <h3>{{ $card['title'] ?? __('Smart offer') }}</h3>
                    <p>{{ $card['body'] ?? '' }}</p>
                </div>
            </article>
        @endforeach
    </div>

    <style>
        .behavioral-offers-grid{display:grid;gap:1rem;margin:1.25rem 0}
        .behavioral-offer-card{display:flex;gap:1rem;align-items:flex-start;padding:1rem 1.05rem;border-radius:1rem;border:1px solid color-mix(in srgb,var(--lc-border) 76%,white);background:#fff;box-shadow:0 14px 34px rgba(15,23,42,.06)}
        .behavioral-offer-card__icon{width:2.75rem;height:2.75rem;border-radius:.9rem;display:grid;place-items:center;font-size:1.1rem;flex:0 0 auto}
        .behavioral-offer-card h3{font-size:1rem;margin:0 0 .3rem;font-weight:800}
        .behavioral-offer-card p{margin:0;color:var(--lc-muted);font-size:.93rem;line-height:1.65}
        .behavioral-offer-card--primary .behavioral-offer-card__icon{background:color-mix(in srgb,var(--lc-primary) 12%,white);color:var(--lc-primary-dark)}
        .behavioral-offer-card--success .behavioral-offer-card__icon{background:rgba(34,197,94,.12);color:#15803d}
        .behavioral-offer-card--warning .behavioral-offer-card__icon{background:rgba(245,158,11,.13);color:#b45309}
    </style>
@endif
