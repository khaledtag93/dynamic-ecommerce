@php
    $data = $section['data'] ?? [];
    $categories = collect($data['categories'] ?? $section['items'] ?? []);
    $title = $data['title'] ?? __('Shop by category');
    $subtitle = $data['subtitle'] ?? __('Find the right department faster');
    $empty = $data['empty'] ?? __('No categories are visible yet.');
@endphp

<section id="{{ $data['key'] ?? 'categories' }}" class="lc-home-section lc-home-section--muted">
    <div class="container">
        <div class="lc-section-head">
            <div class="lc-section-head__copy">
                <span class="lc-section-kicker">{{ $subtitle }}</span>
                <h2 class="lc-section-title">{{ $title }}</h2>
                <p class="lc-section-description mb-0">{{ __('Jump straight to the electronics department you need and compare products faster.') }}</p>
            </div>
        </div>

        @if($categories->isNotEmpty())
            <div class="retail-category-showcase">
                @foreach($categories as $category)
                    @php
                        $cover = $category->image_url ?: ($category->image ? asset('uploads/category/' . $category->image) : null);
                        $productCount = method_exists($category, 'products') ? $category->products()->where('status', true)->count() : null;
                    @endphp
                    <a href="{{ route('category.products', $category->id) }}" class="retail-category-tile {{ $loop->first ? 'retail-category-tile--featured' : '' }}">
                        <div class="retail-category-tile__media">
                            @if($cover)
                                <img src="{{ $cover }}" alt="{{ $category->name }}">
                            @else
                                <div class="retail-category-tile__placeholder"><i class="bi bi-phone"></i></div>
                            @endif
                        </div>
                        <div class="retail-category-tile__content">
                            <span>{{ __('Department') }}</span>
                            <h3>{{ $category->name }}</h3>
                            <p>{{ \Illuminate\Support\Str::limit($category->description ?: __('Browse products, offers, and available items in this department.'), 78) }}</p>
                            <div class="retail-category-tile__meta">
                                <strong>{{ $productCount !== null ? trans_choice(':count product|:count products', $productCount, ['count' => $productCount]) : __('Browse category') }}</strong>
                                <i class="bi bi-arrow-up-right"></i>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        @else
            <div class="lc-section-empty">
                <div class="lc-section-empty__icon"><i class="bi bi-grid-3x3-gap"></i></div>
                <h3 class="h5 fw-bold mb-2">{{ $empty }}</h3>
                <p class="text-muted mb-0">{{ __('Add visible categories from the admin panel to help customers browse the store.') }}</p>
            </div>
        @endif
    </div>
</section>

@once
@push('styles')
<style>
.retail-category-showcase{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:1rem}.retail-category-tile{position:relative;min-height:330px;border-radius:28px;overflow:hidden;background:#111827;color:#fff;box-shadow:0 22px 58px rgba(15,23,42,.12);isolation:isolate}.retail-category-tile:hover{color:#fff;transform:translateY(-4px)}.retail-category-tile--featured{grid-column:span 2}.retail-category-tile__media,.retail-category-tile__media img,.retail-category-tile__placeholder{position:absolute;inset:0;width:100%;height:100%}.retail-category-tile__media img{object-fit:cover;transition:transform .42s ease}.retail-category-tile:hover .retail-category-tile__media img{transform:scale(1.055)}.retail-category-tile__placeholder{display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#111827,color-mix(in srgb,var(--lc-primary) 35%,#020617));font-size:3rem;color:rgba(255,255,255,.8)}.retail-category-tile::after{content:"";position:absolute;inset:0;background:linear-gradient(180deg,rgba(2,6,23,.06),rgba(2,6,23,.76));z-index:1}.retail-category-tile__content{position:relative;z-index:2;height:100%;display:flex;flex-direction:column;justify-content:flex-end;padding:1.35rem}.retail-category-tile__content span{width:max-content;padding:.38rem .65rem;border-radius:999px;background:rgba(255,255,255,.16);border:1px solid rgba(255,255,255,.18);font-weight:900;font-size:.78rem;margin-bottom:.75rem}.retail-category-tile__content h3{font-weight:950;font-size:clamp(1.25rem,2vw,2.15rem);line-height:1.08;letter-spacing:-.04em;margin:0 0 .55rem}.retail-category-tile__content p{color:rgba(255,255,255,.75);line-height:1.65;margin-bottom:1rem}.retail-category-tile__meta{display:flex;align-items:center;justify-content:space-between;gap:1rem}.retail-category-tile__meta strong{font-size:.9rem}.retail-category-tile__meta i{width:40px;height:40px;border-radius:999px;display:flex;align-items:center;justify-content:center;background:#fff;color:#111827}@media(max-width:1199.98px){.retail-category-showcase{grid-template-columns:repeat(3,1fr)}.retail-category-tile--featured{grid-column:span 2}}@media(max-width:991.98px){.retail-category-showcase{grid-template-columns:repeat(2,1fr)}}@media(max-width:575.98px){.retail-category-showcase{grid-template-columns:1fr}.retail-category-tile--featured{grid-column:span 1}.retail-category-tile{min-height:290px}}
</style>
@endpush
@endonce
