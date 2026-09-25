<section class="mt-5" id="product-reviews">
    <div class="lc-card p-4 p-lg-5">
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-4">
            <div>
                <span class="lc-section-kicker">{{ __('Verified customer feedback') }}</span>
                <h2 class="h3 fw-bold mb-2">{{ __('Product reviews') }}</h2>
                <p class="text-muted mb-0">{{ __('Only approved reviews from customers with a verified purchase are published here.') }}</p>
            </div>
            @if(($reviewCount ?? 0) > 0)
                <div class="text-end">
                    <div class="fs-3 fw-bold">{{ number_format((float) $averageRating, 1) }} / 5</div>
                    <div class="text-warning" aria-label="{{ __('Average rating :rating out of 5', ['rating' => number_format((float) $averageRating, 1)]) }}">
                        {{ str_repeat('★', max(1, min(5, (int) round($averageRating)))) }}
                    </div>
                    <div class="text-muted small">{{ trans_choice(':count approved review|:count approved reviews', $reviewCount, ['count' => $reviewCount]) }}</div>
                </div>
            @endif
        </div>

        <div class="row g-4">
            <div class="col-lg-7">
                @forelse($approvedReviews ?? collect() as $review)
                    <article class="border rounded-4 p-3 p-lg-4 mb-3">
                        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-2">
                            <div>
                                <div class="fw-bold">{{ $review->user?->name ?? __('Customer') }}</div>
                                <div class="d-flex align-items-center gap-2 flex-wrap mt-1">
                                    @if($review->verified)
                                        <span class="badge rounded-pill lc-badge-success">{{ __('Verified purchase') }}</span>
                                    @endif
                                    <span class="text-muted small">{{ optional($review->created_at)->format('d M Y') }}</span>
                                </div>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold">{{ $review->rating }} / 5</div>
                                <div class="text-warning" aria-label="{{ __(':rating out of 5 stars', ['rating' => $review->rating]) }}">{{ str_repeat('★', $review->rating) }}</div>
                            </div>
                        </div>
                        @if($review->comment)
                            <p class="mb-0 text-muted">{{ $review->comment }}</p>
                        @endif
                    </article>
                @empty
                    <div class="border rounded-4 p-4 text-center">
                        <i class="bi bi-chat-square-heart fs-2 text-muted"></i>
                        <h3 class="h5 fw-bold mt-3 mb-2">{{ __('No approved reviews yet') }}</h3>
                        <p class="text-muted mb-0">{{ __('Verified customers can submit the first review after a completed purchase.') }}</p>
                    </div>
                @endforelse
            </div>

            <div class="col-lg-5">
                <div class="border rounded-4 p-3 p-lg-4 h-100" data-review-workspace>
                    @auth
                        @if($currentUserReview)
                            <div class="d-flex justify-content-between gap-3 align-items-start mb-3" data-review-summary>
                                <div>
                                    <div class="fw-bold">{{ __('Your review') }}</div>
                                    <div class="text-muted small">{{ __('Edits are moderated again before they are published.') }}</div>
                                </div>
                                <span data-review-status class="lc-status-badge {{ $currentUserReview->status === \App\Models\ProductReview::STATUS_APPROVED ? 'lc-badge-success' : ($currentUserReview->status === \App\Models\ProductReview::STATUS_REJECTED ? 'lc-badge-danger' : 'lc-badge-processing') }}">
                                    <span data-review-status-label>{{ $currentUserReview->status_label }}</span>
                                </span>
                            </div>
                            @if($currentUserReview->status === \App\Models\ProductReview::STATUS_REJECTED && $currentUserReview->moderation_note)
                                <div class="alert alert-warning rounded-4 small">
                                    <strong>{{ __('Moderation note') }}:</strong> {{ $currentUserReview->moderation_note }}
                                </div>
                            @endif
                        @endif

                        @if($canReview)
                            <form method="POST" action="{{ route('reviews.store', $product) }}" data-submit-loading data-review-live>
                                @csrf
                                <div class="mb-3">
                                    <label class="form-label fw-bold" for="reviewRating">{{ __('Rating') }}</label>
                                    <select id="reviewRating" name="rating" class="form-select lc-form-select" required>
                                        <option value="">{{ __('Choose a rating') }}</option>
                                        @foreach([5,4,3,2,1] as $rating)
                                            <option value="{{ $rating }}" @selected((int) old('rating', $currentUserReview?->rating) === $rating)>
                                                {{ $rating }} / 5 — {{ trans_choice(':count star|:count stars', $rating, ['count' => $rating]) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold" for="reviewComment">{{ __('Review') }}</label>
                                    <textarea
                                        id="reviewComment"
                                        name="comment"
                                        rows="5"
                                        maxlength="2000"
                                        class="form-control lc-form-control"
                                        placeholder="{{ __('Share useful details about the product and your experience.') }}"
                                    >{{ old('comment', $currentUserReview?->comment) }}</textarea>
                                    @error('review')
                                        <div class="text-danger small mt-2">{{ $message }}</div>
                                    @enderror
                                    @error('rating')
                                        <div class="text-danger small mt-2">{{ $message }}</div>
                                    @enderror
                                    @error('comment')
                                        <div class="text-danger small mt-2">{{ $message }}</div>
                                    @enderror
                                </div>
                                <button type="submit" class="btn lc-btn-primary w-100" data-loading-text="{{ __('Submitting...') }}">
                                    {{ $currentUserReview ? __('Update review') : __('Submit review') }}
                                </button>
                            </form>

                            @if($currentUserReview)
                                <form
                                    method="POST"
                                    action="{{ route('reviews.destroy', $product) }}"
                                    class="mt-2"
                                    data-confirm-title="{{ __('Remove review') }}"
                                    data-confirm-message="{{ __('Remove your review from this product?') }}"
                                    data-confirm-subtitle="{{ __('This removes your submitted review record. You can submit a new review later while the purchase remains eligible.') }}"
                                    data-confirm-ok="{{ __('Remove review') }}"
                                    data-confirm-cancel="{{ __('Keep review') }}"
                                    data-submit-loading
                                    data-review-delete-live
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn lc-btn-danger-soft w-100" data-loading-text="{{ __('Removing...') }}">{{ __('Remove review') }}</button>
                                </form>
                            @endif
                        <div class="small mt-3 d-none" role="status" aria-live="polite" data-review-live-status></div>
                        @else
                            <div class="text-center py-3">
                                <i class="bi bi-patch-check fs-2 text-muted"></i>
                                <h3 class="h5 fw-bold mt-3 mb-2">{{ __('Verified purchase required') }}</h3>
                                <p class="text-muted mb-0">{{ __('You can review this product after a paid purchase containing this item is recorded on your account.') }}</p>
                            </div>
                        @endif
                    @else
                        <div class="text-center py-3">
                            <i class="bi bi-person-check fs-2 text-muted"></i>
                            <h3 class="h5 fw-bold mt-3 mb-2">{{ __('Purchased this product?') }}</h3>
                            <p class="text-muted mb-3">{{ __('Sign in to submit a review after your verified purchase.') }}</p>
                            <a href="{{ route('login') }}" class="btn lc-btn-primary">{{ __('Login') }}</a>
                        </div>
                    @endauth
                </div>
            </div>
        </div>
    </div>
</section>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const workspace = document.querySelector('[data-review-workspace]');
    if (!workspace || typeof window.fetch !== 'function') return;

    const statusNode = workspace.querySelector('[data-review-live-status]');
    const setStatus = (message, isError = false) => {
        if (!statusNode) return;
        statusNode.textContent = message || '';
        statusNode.classList.toggle('d-none', !message);
        statusNode.classList.toggle('text-danger', isError);
        statusNode.classList.toggle('text-success', !isError && Boolean(message));
    };
    const request = async (form, method) => {
        const response = await fetch(form.action, {
            method,
            headers: {
                'Accept': 'application/json',
                'X-Review-Live': '1',
                'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value,
            },
            credentials: 'same-origin',
            body: method === 'DELETE' ? null : new FormData(form),
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) {
            const firstError = Object.values(payload.errors || {}).flat()[0];
            throw new Error(firstError || payload.message || @json(__('Could not save your review. Please try again.')));
        }
        return payload;
    };

    workspace.addEventListener('submit', async function (event) {
        const form = event.target.closest('[data-review-live], [data-review-delete-live]');
        if (!form) return;
        if (form.matches('[data-review-delete-live]') && form.dataset.confirmed !== '1') return;

        event.preventDefault();
        event.stopImmediatePropagation();
        const button = event.submitter || form.querySelector('button[type="submit"]');
        if (button) button.disabled = true;

        try {
            const deleting = form.matches('[data-review-delete-live]');
            const payload = await request(form, deleting ? 'DELETE' : 'POST');
            setStatus(payload.message);

            if (deleting) {
                window.location.reload();
                return;
            }

            let summary = workspace.querySelector('[data-review-summary]');
            if (!summary && payload.review) {
                summary = document.createElement('div');
                summary.className = 'd-flex justify-content-between gap-3 align-items-start mb-3';
                summary.dataset.reviewSummary = '';
                summary.innerHTML = '<div><div class="fw-bold">' + @json(__('Your review')) + '</div><div class="text-muted small">' + @json(__('Edits are moderated again before they are published.')) + '</div></div><span data-review-status class="lc-status-badge lc-badge-processing"><span data-review-status-label></span></span>';
                form.before(summary);
            }
            const label = workspace.querySelector('[data-review-status-label]');
            const badge = workspace.querySelector('[data-review-status]');
            if (label && payload.review?.status_label) label.textContent = payload.review.status_label;
            if (badge && payload.review?.status) {
                badge.className = 'lc-status-badge ' + (payload.review.status === 'approved' ? 'lc-badge-success' : (payload.review.status === 'rejected' ? 'lc-badge-danger' : 'lc-badge-processing'));
            }
            if (button) button.disabled = false;
        } catch (error) {
            if (button) button.disabled = false;
            setStatus(error.message, true);
        }
    }, true);
});
</script>
@endpush
