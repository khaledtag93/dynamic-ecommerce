<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Services\Commerce\StoreSettingsService;
use App\Support\HomepageSectionBuilder;
use App\Services\Commerce\AIRecommendationEngine;
use App\Services\Commerce\SmartMerchandisingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FrontendController extends Controller
{
    public function __construct(
        protected StoreSettingsService $settingsService,
        protected SmartMerchandisingService $smartMerchandisingService,
        protected AIRecommendationEngine $aiRecommendationEngine,
    ) {
    }

    public function index()
    {
        $storeSettings = $this->settingsService->all();

        $categoryLimit = $this->intSetting($storeSettings, 'home_categories_limit', 8);
        $featuredCategoryLimit = $this->intSetting($storeSettings, 'home_featured_categories_limit', 4);
        $featuredLimit = $this->intSetting($storeSettings, 'home_featured_products_limit', 8);
        $manualFeaturedLimit = $this->intSetting($storeSettings, 'home_manual_featured_products_limit', 8);
        $latestLimit = $this->intSetting($storeSettings, 'home_latest_products_limit', 8);
        $bestSellersLimit = $this->intSetting($storeSettings, 'home_best_sellers_limit', 8);
        $onSaleLimit = $this->intSetting($storeSettings, 'home_on_sale_products_limit', 8);

        $categories = Category::query()
            ->where('status', false)
            ->latest('id')
            ->take($categoryLimit)
            ->get();

        $featuredCategories = $this->featuredCategories($storeSettings, $featuredCategoryLimit);

        $baseProductQuery = Product::query()
            ->with(['translations', 'category.translations', 'mainImage', 'productImages', 'defaultVariant', 'activeVariants'])
            ->where('status', true);

        $featuredProducts = (clone $baseProductQuery)
            ->when(
                Schema::hasColumn('products', 'is_featured'),
                fn ($query) => $query->orderByDesc('is_featured')
            )
            ->latest('id')
            ->take($featuredLimit)
            ->get();

        $manualFeaturedProducts = $this->manualFeaturedProducts($baseProductQuery, $storeSettings, $manualFeaturedLimit);

        $latestProducts = (clone $baseProductQuery)
            ->latest('id')
            ->take($latestLimit)
            ->get();

        $onSaleProducts = (clone $baseProductQuery)
            ->where(function ($query) {
                $query->where(function ($nested) {
                    $nested->whereNotNull('sale_price')
                        ->where('sale_price', '>', 0)
                        ->whereColumn('sale_price', '<', 'base_price');
                });

                if (Schema::hasTable('product_variants')) {
                    $query->orWhereHas('activeVariants', function ($variantQuery) {
                        $variantQuery->whereNotNull('sale_price')
                            ->where('sale_price', '>', 0)
                            ->whereColumn('sale_price', '<', 'price');
                    });
                }
            })
            ->latest('id')
            ->take($onSaleLimit)
            ->get();

        $bestSellers = $this->bestSellers($baseProductQuery, $bestSellersLimit);

        $merchandisingCollections = $this->smartMerchandisingService->homeCollections(8, 8);
        $aiRecommendations = $this->aiRecommendationEngine->forHome(8);

        $homeSections = HomepageSectionBuilder::build(
            $storeSettings,
            $categories,
            $featuredProducts,
            $latestProducts,
            $bestSellers,
            $onSaleProducts,
            $featuredCategories,
            $manualFeaturedProducts
        );

        return view('frontend.index', [
            'categories' => $categories,
            'featuredCategories' => $featuredCategories,
            'featuredProducts' => $featuredProducts,
            'manualFeaturedProducts' => $manualFeaturedProducts,
            'latestProducts' => $latestProducts,
            'bestSellers' => $bestSellers,
            'onSaleProducts' => $onSaleProducts,
            'products' => $featuredProducts,
            'storeSettings' => $storeSettings,
            'homeSections' => $homeSections,
            'continueShoppingProducts' => $merchandisingCollections['continueShopping'],
            'recommendedForYouProducts' => $merchandisingCollections['recommendedForYou'],
            'aiRecommendedProducts' => $aiRecommendations['products'],
            'aiRecommendationInsight' => $aiRecommendations['insight'],
        ]);
    }

    public function showCategoryProducts(Request $request, $id)
    {
        $category = Category::query()
            ->whereKey($id)
            ->where('status', false)
            ->firstOrFail();

        $allowedSorts = ['latest', 'price_low_high', 'price_high_low', 'name_az'];
        $sort = in_array($request->string('sort')->toString(), $allowedSorts, true)
            ? $request->string('sort')->toString()
            : 'latest';

        $filters = [
            'q' => trim((string) $request->string('q')),
            'availability' => in_array($request->string('availability')->toString(), ['all', 'in_stock'], true)
                ? $request->string('availability')->toString()
                : 'all',
            'offer' => in_array($request->string('offer')->toString(), ['all', 'on_sale'], true)
                ? $request->string('offer')->toString()
                : 'all',
            'sort' => $sort,
        ];

        $baseQuery = Product::query()
            ->with(['translations', 'category.translations', 'mainImage', 'productImages', 'defaultVariant', 'activeVariants.attributes.attribute'])
            ->where('category_id', $category->id)
            ->where('status', true);

        $products = $this->applyCategoryFilters(clone $baseQuery, $filters)
            ->paginate(12)
            ->withQueryString();

        $categoryStats = [
            'total' => (clone $baseQuery)->count(),
            'in_stock' => (clone $baseQuery)->where(function (Builder $query) {
                $query->where(function (Builder $simple) {
                    $simple->where('has_variants', false)->where('quantity', '>', 0);
                })->orWhereHas('activeVariants', function (Builder $variantQuery) {
                    $variantQuery->where('stock', '>', 0);
                });
            })->count(),
            'on_sale' => (clone $baseQuery)->where(function (Builder $query) {
                $query->where(function (Builder $simple) {
                    $simple->whereNotNull('sale_price')
                        ->where('sale_price', '>', 0)
                        ->whereColumn('sale_price', '<', 'base_price');
                })->orWhereHas('activeVariants', function (Builder $variantQuery) {
                    $variantQuery->whereNotNull('sale_price')
                        ->where('sale_price', '>', 0)
                        ->whereColumn('sale_price', '<', 'price');
                });
            })->count(),
        ];

        return view('frontend.products.by_category', [
            'category' => $category,
            'products' => $products,
            'filters' => $filters,
            'categoryStats' => $categoryStats,
        ]);
    }

    protected function applyCategoryFilters(Builder $query, array $filters): Builder
    {
        if ($filters['q'] !== '') {
            $search = $filters['q'];

            $query->where(function (Builder $builder) use ($search) {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('translations', function (Builder $translations) use ($search) {
                        $translations
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('description', 'like', "%{$search}%");
                    });
            });
        }

        if ($filters['availability'] === 'in_stock') {
            $query->where(function (Builder $builder) {
                $builder->where(function (Builder $simple) {
                    $simple->where('has_variants', false)->where('quantity', '>', 0);
                })->orWhereHas('activeVariants', function (Builder $variantQuery) {
                    $variantQuery->where('stock', '>', 0);
                });
            });
        }

        if ($filters['offer'] === 'on_sale') {
            $query->where(function (Builder $builder) {
                $builder->where(function (Builder $simple) {
                    $simple->whereNotNull('sale_price')
                        ->where('sale_price', '>', 0)
                        ->whereColumn('sale_price', '<', 'base_price');
                })->orWhereHas('activeVariants', function (Builder $variantQuery) {
                    $variantQuery->whereNotNull('sale_price')
                        ->where('sale_price', '>', 0)
                        ->whereColumn('sale_price', '<', 'price');
                });
            });
        }

        return match ($filters['sort']) {
            'price_low_high' => $query->orderByRaw('COALESCE(NULLIF(sale_price, 0), base_price, 0) asc')->latest('id'),
            'price_high_low' => $query->orderByRaw('COALESCE(NULLIF(sale_price, 0), base_price, 0) desc')->latest('id'),
            'name_az' => $query->orderBy('name')->latest('id'),
            default => $query->latest('id'),
        };
    }

    protected function featuredCategories(array $settings, int $limit): Collection
    {
        $source = (string) ($settings['home_featured_categories_source'] ?? 'manual');
        $ids = $this->parseIds($settings['home_featured_categories_ids'] ?? '');

        if ($source === 'manual' && ! empty($ids)) {
            $categories = Category::query()
                ->where('status', false)
                ->whereIn('id', $ids)
                ->get()
                ->keyBy('id');

            return collect($ids)
                ->map(fn (int $id) => $categories->get($id))
                ->filter()
                ->take($limit)
                ->values();
        }

        return Category::query()
            ->where('status', false)
            ->latest('id')
            ->take($limit)
            ->get();
    }

    protected function manualFeaturedProducts($baseProductQuery, array $settings, int $limit): Collection
    {
        $ids = $this->parseIds($settings['home_manual_featured_products_ids'] ?? '');

        if (empty($ids)) {
            return collect();
        }

        $products = (clone $baseProductQuery)
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        return collect($ids)
            ->map(fn (int $id) => $products->get($id))
            ->filter()
            ->take($limit)
            ->values();
    }

    protected function bestSellers($baseProductQuery, int $limit): Collection
    {
        if (! Schema::hasTable('order_items')) {
            return (clone $baseProductQuery)->latest('id')->take($limit)->get();
        }

        $bestSellerIds = DB::table('order_items')
            ->select('product_id', DB::raw('SUM(quantity) as sold_qty'))
            ->whereNotNull('product_id')
            ->groupBy('product_id')
            ->orderByDesc('sold_qty')
            ->limit($limit)
            ->pluck('product_id')
            ->filter()
            ->values();

        if ($bestSellerIds->isEmpty()) {
            return (clone $baseProductQuery)
                ->latest('id')
                ->take($limit)
                ->get();
        }

        $products = (clone $baseProductQuery)
            ->whereIn('id', $bestSellerIds->all())
            ->get()
            ->keyBy('id');

        return $bestSellerIds
            ->map(fn ($id) => $products->get($id))
            ->filter()
            ->values();
    }

    protected function parseIds(null|string|array $value): array
    {
        if (is_array($value)) {
            return collect($value)
                ->map(fn ($item) => is_numeric($item) ? (int) $item : null)
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        return collect(preg_split('/[^0-9]+/', (string) $value) ?: [])
            ->map(fn ($item) => is_numeric($item) ? (int) $item : null)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function intSetting(array $settings, string $key, int $default): int
    {
        $value = $settings[$key] ?? $default;
        $intValue = is_numeric($value) ? (int) $value : $default;

        return max(1, min(24, $intValue));
    }
}
