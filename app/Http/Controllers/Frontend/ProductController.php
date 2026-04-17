<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Commerce\AIRecommendationEngine;
use App\Services\Commerce\BehaviorTrackingService;
use App\Services\Commerce\OfferAutomationService;
use App\Services\Commerce\ProductRecommendationService;
use App\Services\Commerce\SmartMerchandisingService;

class ProductController extends Controller
{
    public function __construct(
        protected ProductRecommendationService $recommendationService,
        protected SmartMerchandisingService $smartMerchandisingService,
        protected BehaviorTrackingService $behaviorTrackingService,
        protected OfferAutomationService $offerAutomationService,
        protected AIRecommendationEngine $aiRecommendationEngine,
    ) {
    }

    public function show(Product $product)
    {
        if ((int) $product->status !== 1) {
            abort(404);
        }

        $product->load([
            'translations',
            'category.translations',
            'productImages',
            'mainImage',
            'activeVariants.attributes.attribute',
            'defaultVariant.attributes.attribute',
            'bundleProducts',
            'addonProducts',
            'relatedProducts',
        ]);

        $this->smartMerchandisingService->recordProductView($product);
        $this->behaviorTrackingService->trackProductView($product);
        $recommendations = $this->recommendationService->forProduct($product, 4);
        $merchandising = $this->smartMerchandisingService->forProduct($product, 4);
        $behavioralOffers = $this->offerAutomationService->forProduct($product);
        $aiRecommendations = $this->aiRecommendationEngine->forProduct($product, 4);

        return view('frontend.products.show', [
            'product' => $product,
            'bundleProducts' => $recommendations['bundleProducts'],
            'addonProducts' => $recommendations['addonProducts'],
            'relatedProducts' => $recommendations['relatedProducts'],
            'recentlyViewedProducts' => $merchandising['recentlyViewed'],
            'personalizedProducts' => $merchandising['recommendedForYou'],
            'offerSignals' => $merchandising['offerSignals'],
            'behavioralOffers' => $behavioralOffers,
            'aiRecommendedProducts' => $aiRecommendations['products'],
            'aiRecommendationInsight' => $aiRecommendations['insight'],
        ]);
    }
}
