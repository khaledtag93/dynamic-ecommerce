<div data-live-results aria-busy="false">
    <div class="admin-card">
        <div class="admin-card-body">
            <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-3">
                <div>
                    <h4 class="mb-1">{{ __('Review queue') }}</h4>
                    <div class="text-muted small">{{ __('Showing :count review(s) on this page.', ['count' => $reviews->count()]) }}</div>
                </div>
            </div>

            @if($reviews->count())
                <div class="table-responsive">
                    <table class="table admin-table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('Product') }}</th>
                                <th>{{ __('Customer') }}</th>
                                <th>{{ __('Rating') }}</th>
                                <th>{{ __('Review') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th>{{ __('Submitted') }}</th>
                                <th class="text-end">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($reviews as $review)
                                <tr>
                                    <td>
                                        @if($review->product)
                                            <a href="{{ route('frontend.products.show', $review->product) }}" target="_blank" rel="noopener" class="fw-semibold text-decoration-none">
                                                {{ $review->product->name }}
                                            </a>
                                        @else
                                            <span class="text-muted">{{ __('Deleted product') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="fw-semibold">{{ $review->user?->name ?? __('Deleted customer') }}</div>
                                        <div class="text-muted small">{{ $review->user?->email ?? '—' }}</div>
                                        @if($review->verified)
                                            <span class="badge rounded-pill badge-soft-success mt-1">{{ __('Verified purchase') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="fw-bold">{{ $review->rating }} / 5</div>
                                        <div class="text-warning" aria-label="{{ __(':rating out of 5 stars', ['rating' => $review->rating]) }}">{{ str_repeat('★', $review->rating) }}</div>
                                    </td>
                                    <td style="min-width:260px;max-width:420px">
                                        <div class="text-wrap">{{ $review->comment ?: __('No written comment.') }}</div>
                                        @if($review->moderation_note)
                                            <div class="small text-muted mt-2">{{ __('Moderation note') }}: {{ $review->moderation_note }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge admin-status-badge {{ $review->status_badge_class }}">{{ $review->status_label }}</span>
                                        @if($review->moderator)
                                            <div class="small text-muted mt-1">{{ __('By :name', ['name' => $review->moderator->name]) }}</div>
                                        @endif
                                    </td>
                                    <td>{{ optional($review->created_at)->format('d M Y, H:i') }}</td>
                                    <td class="text-end">
                                        <div class="d-flex justify-content-end gap-2 flex-wrap">
                                            @if($review->status !== \App\Models\ProductReview::STATUS_APPROVED)
                                                <form method="POST" action="{{ route('admin.reviews.moderate', $review) }}" data-submit-loading
                                            data-confirm-title="{{ __('Publish review') }}"
                                            data-confirm-message="{{ __('Approve and publish this review?') }}"
                                            data-confirm-subtitle="{{ __('The review will become visible on the storefront immediately.') }}"
                                            data-confirm-ok="{{ __('Publish review') }}"
                                            data-confirm-cancel="{{ __('Keep reviewing') }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="status" value="{{ \App\Models\ProductReview::STATUS_APPROVED }}">
                                                    <button type="submit" class="btn btn-sm btn-success" data-loading-text="{{ __('Publishing...') }}">{{ __('Approve') }}</button>
                                                </form>
                                            @endif
                                            @if($review->status !== \App\Models\ProductReview::STATUS_REJECTED)
                                                <form method="POST" action="{{ route('admin.reviews.moderate', $review) }}" class="d-flex gap-2 align-items-center flex-wrap justify-content-end" data-submit-loading
                                            data-confirm-title="{{ __('Reject review') }}"
                                            data-confirm-message="{{ __('Reject and hide this review?') }}"
                                            data-confirm-subtitle="{{ __('The review will remain hidden from the storefront and the moderation note will be recorded.') }}"
                                            data-confirm-ok="{{ __('Reject review') }}"
                                            data-confirm-cancel="{{ __('Keep reviewing') }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="status" value="{{ \App\Models\ProductReview::STATUS_REJECTED }}">
                                                    <input
                                                        type="text"
                                                        name="moderation_note"
                                                        class="form-control form-control-sm"
                                                        style="max-width:220px"
                                                        maxlength="1000"
                                                        placeholder="{{ __('Optional rejection note') }}"
                                                        aria-label="{{ __('Optional rejection note') }}"
                                                    >
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" data-loading-text="{{ __('Rejecting...') }}">{{ __('Reject') }}</button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $reviews->links() }}
                </div>
            @else
                <div class="admin-empty-state py-5">
                    <div class="empty-icon"><i class="mdi mdi-message-star-outline"></i></div>
                    <h5 class="mb-2">{{ __('No reviews match these filters') }}</h5>
                    <p class="text-muted mb-0">{{ __('New verified-purchase reviews will appear here for moderation.') }}</p>
                </div>
            @endif
        </div>
    </div>
</div>
