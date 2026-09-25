<div data-live-results>
    <div class="admin-card">
        <div class="table-responsive admin-table-wrap">
            <table class="table admin-table align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Case') }}</th>
                        <th>{{ __('Customer / Order') }}</th>
                        <th>{{ __('Priority') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Owner') }}</th>
                        <th>{{ __('Updated') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($cases as $supportCase)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $supportCase->case_number }}</div>
                                <div class="text-muted small">{{ \Illuminate\Support\Str::limit($supportCase->subject, 70) }}</div>
                            </td>
                            <td>
                                <div class="fw-semibold">{{ $supportCase->customer?->name ?? __('Guest / unlinked') }}</div>
                                <div class="text-muted small">{{ $supportCase->customer?->email ?? '—' }}</div>
                                @if($supportCase->order)
                                    <div class="text-muted small">{{ __('Order') }}: {{ $supportCase->order->order_number }}</div>
                                @endif
                            </td>
                            <td><span class="badge admin-status-badge {{ $supportCase->priority_badge_class }}">{{ $supportCase->priority_label }}</span></td>
                            <td><span class="badge admin-status-badge {{ $supportCase->status_badge_class }}">{{ $supportCase->status_label }}</span></td>
                            <td>{{ $supportCase->assignee?->name ?? __('Unassigned') }}</td>
                            <td><div>{{ $supportCase->updated_at?->format('Y-m-d H:i') }}</div><div class="text-muted small">{{ $supportCase->updated_at?->diffForHumans() }}</div></td>
                            <td class="text-end"><a href="{{ route('admin.support.show', $supportCase) }}" class="btn btn-sm btn-outline-primary">{{ __('Open case') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-5">{{ __('No support cases match these filters.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($cases->hasPages())
            <div class="admin-card-body border-top">{{ $cases->links() }}</div>
        @endif
    </div>
</div>
