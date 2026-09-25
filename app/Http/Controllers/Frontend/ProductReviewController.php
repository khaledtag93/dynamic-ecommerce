<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Commerce\ProductReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProductReviewController extends Controller
{
    public function __construct(protected ProductReviewService $productReviewService)
    {
    }

    public function store(Request $request, Product $product): RedirectResponse|JsonResponse
    {
        abort_unless((int) $product->status === 1, 404);

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $review = $this->productReviewService->submit($request->user(), $product, $validated);
            $message = __('Your review was submitted for moderation.');

            if ($request->expectsJson() || $request->header('X-Review-Live') === '1') {
                return response()->json([
                    'message' => $message,
                    'review' => [
                        'rating' => $review->rating,
                        'comment' => $review->comment,
                        'status' => $review->status,
                        'status_label' => $review->status_label,
                    ],
                ]);
            }

            return redirect()
                ->route('frontend.products.show', $product)
                ->with('success', $message);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }
    }

    public function destroy(Request $request, Product $product): RedirectResponse|JsonResponse
    {
        $this->productReviewService->deleteOwn($request->user(), $product);
        $message = __('Your review was removed.');

        if ($request->expectsJson() || $request->header('X-Review-Live') === '1') {
            return response()->json(['message' => $message]);
        }

        return redirect()
            ->route('frontend.products.show', $product)
            ->with('success', $message);
    }
}
