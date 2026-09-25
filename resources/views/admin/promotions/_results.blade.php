@php
    $sort = $filters['sort'] ?? 'priority';
    $direction = $filters['direction'] ?? 'desc';
    $sortLink = function (string $column) use ($filters, $sort, $direction) {
        $query = array_filter([
            'search' => $filters['search'] ?? null,
            'type' => $filters['type'] ?? null,
            'status' => $filters['status'] ?? null,
            'schedule' => $filters['schedule'] ?? null,
            'per_page' => $filters['per_page'] ?? 20,
            'sort' => $column,
            'direction' => $sort === $column && $direction === 'asc' ? 'desc' : 'asc',
        ], fn ($value) => $value !== null && $value !== '');

        return route('admin.promotions.index', $query);
    };
@endphp

<div data-live-results aria-busy="false">
<div class="admin-card mb-4">
    <div class="admin-card-body">
        <div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-center gap-3">
            <div>
                <h4 class="mb-1">{{ __('Promotion schedule') }}</h4>
                <p class="text-muted small mb-0">{{ __('Separate live campaigns from upcoming and expired rules before changing pricing behavior.') }}</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('admin.promotions.index',['schedule'=>'running']) }}" data-live-link class="btn {{ $filters['schedule']==='running' ? 'btn-primary':'btn-light border' }} btn-sm">{{ __('Running now') }} · {{ $queueStats['running'] }}</a>
                <a href="{{ route('admin.promotions.index',['schedule'=>'upcoming']) }}" data-live-link class="btn {{ $filters['schedule']==='upcoming' ? 'btn-primary':'btn-light border' }} btn-sm">{{ __('Upcoming') }} · {{ $queueStats['upcoming'] }}</a>
                <a href="{{ route('admin.promotions.index',['schedule'=>'expired']) }}" data-live-link class="btn {{ $filters['schedule']==='expired' ? 'btn-primary':'btn-light border' }} btn-sm">{{ __('Expired') }} · {{ $queueStats['expired'] }}</a>
            </div>
        </div>
    </div>
</div>

<div class="admin-card">
    <div class="admin-card-body">
        <div class="admin-table-toolbar">
            <div>
                <h4 class="mb-1">{{ __('Promotions list') }}</h4>
                <div class="text-muted small">{{ __('Showing :count promotion rule(s) on this page.', ['count' => $promotions->count()]) }}</div>
            </div>
            @if($filters['search'] || $filters['type'] || $filters['status'] || $filters['schedule'])
                <span class="admin-chip">{{ __('Filtered results') }}</span>
            @endif
        </div>

        <div class="table-responsive">
            <table class="table admin-table align-middle mb-0">
                <thead>
                    <tr>
                        <th><a class="table-sort-btn" data-live-link href="{{ $sortLink('name') }}">{{ __('Name') }} @if($sort === 'name') <i class="mdi {{ $direction === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down' }}"></i> @endif</a></th>
                        <th><a class="table-sort-btn" data-live-link href="{{ $sortLink('type') }}">{{ __('Type') }} @if($sort === 'type') <i class="mdi {{ $direction === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down' }}"></i> @endif</a></th>
                        <th>{{ __('Category') }}</th>
                        <th><a class="table-sort-btn" data-live-link href="{{ $sortLink('discount_value') }}">{{ __('Discount') }} @if($sort === 'discount_value') <i class="mdi {{ $direction === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down' }}"></i> @endif</a></th>
                        <th><a class="table-sort-btn" data-live-link href="{{ $sortLink('priority') }}">{{ __('Priority') }} @if($sort === 'priority') <i class="mdi {{ $direction === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down' }}"></i> @endif</a></th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-end rtl-text-start">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($promotions as $promotion)
                    <tr>
                        <td class="fw-semibold">{{ $promotion->name }}</td>
                        <td>{{ __(\Illuminate\Support\Str::headline(str_replace('_', ' ', $promotion->type))) }}</td>
                        <td>{{ $promotion->category?->name ?: __('All') }}</td>
                        <td>{{ number_format((float) $promotion->discount_value, 2) }}</td>
                        <td>{{ $promotion->priority }}</td>
                        <td><span class="badge admin-status-badge {{ $promotion->is_active ? 'badge-soft-success' : 'badge-soft-secondary' }}">{{ $promotion->is_active ? __('Active') : __('Inactive') }}</span></td>
                        <td class="text-end rtl-text-start">
                            <div class="d-inline-flex gap-2 flex-wrap justify-content-end rtl-justify-start">
                                <a href="{{ route('admin.promotions.edit', $promotion) }}" class="btn-table-icon btn-edit" title="{{ __('Edit promotion') }}"><i class="mdi mdi-pencil-outline"></i></a>
                                <form method="POST" action="{{ route('admin.promotions.destroy', $promotion) }}" data-submit-loading data-confirm-message="{{ __('Delete this promotion rule?') }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-table-icon btn-delete" title="{{ __('Delete promotion') }}" data-loading-text="{{ __('Deleting...') }}"><i class="mdi mdi-trash-can-outline"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">{{ __('No promotion rules yet.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@if($promotions->hasPages())
    <div class="mt-4">{{ $promotions->links() }}</div>
@endif
</div>
