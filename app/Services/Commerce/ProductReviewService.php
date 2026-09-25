<?php

namespace App\Services\Commerce;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductReviewService
{
    public function canReview(User $user, Product $product): bool
    {
        return Order::query()
            ->where('user_id', $user->id)
            ->where('status', '!=', Order::STATUS_CANCELLED)
            ->whereIn('payment_status', [
                Order::PAYMENT_STATUS_PAID,
                Order::PAYMENT_STATUS_PARTIALLY_REFUNDED,
                Order::PAYMENT_STATUS_REFUNDED,
            ])
            ->whereHas('items', fn ($query) => $query->where('product_id', $product->id))
            ->exists();
    }

    public function submit(User $user, Product $product, array $data): ProductReview
    {
        if (! $this->canReview($user, $product)) {
            throw ValidationException::withMessages([
                'review' => __('You can review this product after a verified purchase.'),
            ]);
        }

        return DB::transaction(function () use ($user, $product, $data) {
            $review = ProductReview::query()->firstOrNew([
                'product_id' => $product->id,
                'user_id' => $user->id,
            ]);

            $review->fill([
                'rating' => (int) $data['rating'],
                'comment' => $data['comment'] ?? null,
                'verified' => true,
                'status' => ProductReview::STATUS_PENDING,
                'moderated_by' => null,
                'moderated_at' => null,
                'moderation_note' => null,
            ])->save();

            return $review->fresh();
        });
    }

    public function deleteOwn(User $user, Product $product): void
    {
        ProductReview::query()
            ->where('product_id', $product->id)
            ->where('user_id', $user->id)
            ->delete();
    }

    public function moderate(ProductReview $review, User $moderator, string $status, ?string $note = null): ProductReview
    {
        if (! in_array($status, [ProductReview::STATUS_APPROVED, ProductReview::STATUS_REJECTED], true)) {
            throw ValidationException::withMessages([
                'status' => __('Choose a valid review moderation decision.'),
            ]);
        }

        $review->forceFill([
            'status' => $status,
            'moderated_by' => $moderator->id,
            'moderated_at' => now(),
            'moderation_note' => $note,
        ])->save();

        return $review->fresh();
    }
}
