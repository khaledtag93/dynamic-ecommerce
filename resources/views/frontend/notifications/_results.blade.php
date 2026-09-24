<div data-live-results aria-busy="false">
<div class="lc-grid-shell d-flex flex-column gap-3">
    @forelse($notifications as $notification)
        @php($payload = $notification->data)
        <div class="lc-card p-4">
            <div class="d-flex justify-content-between gap-3 flex-wrap align-items-start">
                <div>
                    <div class="fw-bold mb-1">{{ $payload['title'] ?? __('Notification') }}</div>
                    <div class="text-muted mb-2">{{ $payload['body'] ?? '' }}</div>
                    <div class="small text-muted">{{ optional($notification->created_at)->diffForHumans() }}</div>
                </div>
                <div class="d-flex gap-2">
                    @if(!$notification->read_at)
                        <form method="POST" action="{{ route('notifications.read', $notification) }}">
                            @csrf @method('PATCH')
                            <button class="btn btn-sm btn-outline-secondary rounded-4">{{ !empty($payload['action_url']) ? __('View update') : __('Mark as read') }}</button>
                        </form>
                    @elseif(!empty($payload['action_url']))
                        <a href="{{ $payload['action_url'] }}" class="btn btn-sm btn-outline-secondary rounded-4">{{ __('View update') }}</a>
                    @endif
                </div>
            </div>
        </div>
    @empty
        <div class="lc-card lc-empty-state">
            <div class="lc-empty-icon"><i class="bi bi-bell"></i></div>
            <h3 class="fw-bold mb-2">{{ __('No notifications yet') }}</h3>
            <p class="text-muted mb-0">{{ __('We will keep your order, payment, and delivery updates here.') }}</p>
        </div>
    @endforelse
</div>
@if($notifications->hasPages())<div class="pt-4 d-flex justify-content-center">{{ $notifications->links() }}</div>@endif
</div>