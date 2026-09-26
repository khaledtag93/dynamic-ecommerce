<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductReview;
use App\Services\Commerce\ProductReviewService;
use App\Services\Commerce\AdminActivityLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductReviewController extends Controller
{
    public function __construct(
        protected ProductReviewService $productReviewService,
        protected AdminActivityLogService $adminActivityLogService,
    )
    {
    }

    public function index(Request $request)
    {
        $filters = [
            'search' => trim((string) $request->string('search')),
            'status' => (string) $request->string('status'),
            'rating' => (string) $request->string('rating'),
            'per_page' => max(12, min(100, (int) $request->integer('per_page', 20))),
        ];

        $reviews = ProductReview::query()
            ->with(['product:id,name,slug', 'user:id,name,email', 'moderator:id,name'])
            ->when($filters['search'], function ($query, $search) {
                $query->where(function ($inner) use ($search) {
                    $inner
                        ->where('comment', 'like', "%{$search}%")
                        ->orWhereHas('product', fn ($productQuery) => $productQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('user', function ($userQuery) use ($search) {
                            $userQuery
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            })
            ->when(in_array($filters['status'], ProductReview::statuses(), true), fn ($query) => $query->where('status', $filters['status']))
            ->when(in_array((int) $filters['rating'], [1, 2, 3, 4, 5], true), fn ($query) => $query->where('rating', (int) $filters['rating']))
            ->latest('id')
            ->paginate($filters['per_page'])
            ->withQueryString();

        if ($request->header('X-Live-List') === '1') {
            return response()->view('admin.reviews._results', compact('reviews', 'filters'));
        }

        $stats = [
            'total' => ProductReview::count(),
            'pending' => ProductReview::where('status', ProductReview::STATUS_PENDING)->count(),
            'approved' => ProductReview::where('status', ProductReview::STATUS_APPROVED)->count(),
            'rejected' => ProductReview::where('status', ProductReview::STATUS_REJECTED)->count(),
        ];

        return view('admin.reviews.index', compact('reviews', 'filters', 'stats'));
    }

    public function moderate(Request $request, ProductReview $review): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in([ProductReview::STATUS_APPROVED, ProductReview::STATUS_REJECTED])],
            'moderation_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $oldStatus = $review->status;

        $moderatedReview = $this->productReviewService->moderate(
            $review,
            $request->user(),
            $validated['status'],
            $validated['moderation_note'] ?? null
        );

        $this->adminActivityLogService->log(
            'reviews',
            'product_review_moderated',
            __('Product review moderation changed from :old to :new.', [
                'old' => $oldStatus,
                'new' => $moderatedReview->status,
            ]),
            $request->user()->id,
            $moderatedReview,
            [
                'old_status' => $oldStatus,
                'new_status' => $moderatedReview->status,
                'moderation_note' => $moderatedReview->moderation_note,
            ]
        );

        return back()->with(
            'success',
            $validated['status'] === ProductReview::STATUS_APPROVED
                ? __('Review approved and published.')
                : __('Review rejected and hidden from the storefront.')
        );
    }
}
