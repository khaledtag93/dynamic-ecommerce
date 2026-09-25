<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Commerce\ProductReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProductReviewController extends Controller
{
    public function __construct(protected ProductReviewService $productReviewService)
    {
    }

    public function store(Request $request, Product $product): RedirectResponse
    {
        abort_unless((int) $product->status === 1, 404);

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->productReviewService->submit($request->user(), $product, $validated);

            return redirect()
                ->route('frontend.products.show', $product)
                ->with('success', __('Your review was submitted for moderation.'));
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $this->productReviewService->deleteOwn($request->user(), $product);

        return redirect()
            ->route('frontend.products.show', $product)
            ->with('success', __('Your review was removed.'));
    }
}
