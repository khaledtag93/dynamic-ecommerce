@php
    $data = $section['data'] ?? [];
    $categories = $data['categories'] ?? collect();
    $title = $data['title'] ?? __('Browse categories');
    $subtitle = $data['subtitle'] ?? __('Shop by collection');
    $anchor = $data['key'] ?? 'categories';
    $empty = $data['empty'] ?? __('No visible categories yet.');
    $actionText = $data['action_text'] ?? null;
    $actionLink = $data['action_link'] ?? null;
@endphp

<section id="{{ $anchor }}" class="lc-home-section {{ $anchor === 'categories' ? 'lc-home-section--muted' : '' }}">
    <div class="container">
        <div class="lc-section-shell lc-section-shell--spacious">
            <div class="lc-section-head lc-section-head--split-lg align-items-end">
                <div class="lc-section-head__copy">
                    <span class="lc-section-kicker">{{ $subtitle }}</span>
                    <h2 class="lc-section-title">{{ $title }}</h2>
                    <p class="lc-section-description mb-0">{{ __('Guide shoppers into the right collection quickly so they spend less time searching and more time buying.') }}</p>
                </div>

                <div class="lc-section-head__aside">
                    <div class="lc-inline-stat-card">
                        <div class="lc-inline-stat-card__value">{{ $categories->count() }}</div>
                        <div class="lc-inline-stat-card__label">{{ __('collections') }}</div>
                    </div>

                    @if($actionText && $actionLink)
                        <a href="{{ $actionLink }}" class="btn lc-btn-soft">{{ $actionText }}</a>
                    @endif
                </div>
            </div>

            @if($categories->isNotEmpty())
                <div class="lc-grid lc-grid-categories">
                    @foreach($categories as $category)
                        @php
                            $productCount = method_exists($category, 'products') ? $category->products()->count() : null;
                            $cover = $category->image_url ?: 'https://via.placeholder.com/900x700?text=Category';
                        @endphp

                        <a href="{{ route('category.products', $category->id) }}" class="d-block h-100 text-decoration-none">
                            <article class="lc-card lc-category-card h-100 p-3 p-lg-4">
                                <div class="lc-category-card__media mb-3">
                                    <img src="{{ $cover }}" class="img-fluid lc-category-card__image" alt="{{ $category->name }}">
                                    <span class="lc-category-card__badge">{{ __('Explore') }}</span>
                                </div>

                                <div class="d-flex align-items-start justify-content-between gap-3">
                                    <div>
                                        <h3 class="h5 fw-bold text-dark mb-1">{{ $category->name }}</h3>
                                        <p class="text-muted mb-2">{{ \Illuminate\Support\Str::limit($category->description ?: __('Open this collection and start narrowing down products faster.'), 95) }}</p>
                                        <div class="small fw-semibold text-muted">
                                            <i class="bi bi-box-seam me-1"></i>
                                            {{ $productCount !== null ? trans_choice(':count product|:count products', $productCount, ['count' => $productCount]) : __('Browse category') }}
                                        </div>
                                    </div>
                                    <span class="lc-badge flex-shrink-0"><i class="bi bi-arrow-up-right"></i></span>
                                </div>
                            </article>
                        </a>
                    @endforeach
                </div>
            @else
                <div class="lc-section-empty">
                    <div class="lc-section-empty__icon"><i class="bi bi-grid-3x3-gap"></i></div>
                    <h3 class="h5 fw-bold mb-2">{{ $title }}</h3>
                    <p class="text-muted mb-0">{{ $empty }}</p>
                </div>
            @endif
        </div>
    </div>
</section>
