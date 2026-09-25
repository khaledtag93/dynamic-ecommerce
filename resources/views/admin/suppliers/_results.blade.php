@php
    $sort = $filters['sort'] ?? 'updated_at';
    $direction = $filters['direction'] ?? 'desc';
    $sortLink = function (string $column) use ($filters, $sort, $direction) {
        $query = array_filter([
            'search' => $filters['search'] ?? null,
            'status' => $filters['status'] ?? null,
            'usage' => $filters['usage'] ?? null,
            'per_page' => $filters['per_page'] ?? 12,
            'sort' => $column,
            'direction' => $sort === $column && $direction === 'asc' ? 'desc' : 'asc',
        ], fn ($value) => $value !== null && $value !== '');

        return route('admin.suppliers.index', $query);
    };
@endphp

<div data-live-results aria-busy="false">
<div class="admin-card mb-4"><div class="admin-card-body">
    <div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-center gap-3">
        <div>
            <h4 class="mb-1">{{ __('Supplier operations') }}</h4>
            <p class="text-muted small mb-0">{{ __('Review sourcing relationships, inactive vendors, and suppliers that have not been used yet.') }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('admin.suppliers.index', ['usage' => 'unused']) }}" data-live-link class="btn {{ $filters['usage'] === 'unused' ? 'btn-primary' : 'btn-light border' }} btn-sm">{{ __('Unused') }} · {{ $queueStats['unused'] }}</a>
            <a href="{{ route('admin.suppliers.index', ['status' => 'inactive']) }}" data-live-link class="btn {{ $filters['status'] === 'inactive' ? 'btn-primary' : 'btn-light border' }} btn-sm">{{ __('Inactive') }} · {{ $queueStats['inactive'] }}</a>
        </div>
    </div>
</div></div>

<div class="admin-card"><div class="admin-card-body">
    <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-3">
        <div>
            <h4 class="mb-1">{{ __('Suppliers list') }}</h4>
            <div class="text-muted small">{{ __('Showing :count supplier(s) on this page.', ['count' => $suppliers->count()]) }}</div>
        </div>
        @if($filters['search'] || $filters['status'] || $filters['usage'])
            <span class="admin-chip">{{ __('Filtered results') }}</span>
        @endif
    </div>

    @if($suppliers->count())
        <div class="table-responsive">
            <table class="table admin-table align-middle mb-0">
                <thead>
                    <tr>
                        <th><a class="table-sort-btn" data-live-link href="{{ $sortLink('name') }}">{{ __('Name') }} @if($sort === 'name') <i class="mdi {{ $direction === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down' }}"></i> @endif</a></th>
                        <th><a class="table-sort-btn" data-live-link href="{{ $sortLink('company') }}">{{ __('Company') }} @if($sort === 'company') <i class="mdi {{ $direction === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down' }}"></i> @endif</a></th>
                        <th><a class="table-sort-btn" data-live-link href="{{ $sortLink('email') }}">{{ __('Contact') }} @if($sort === 'email') <i class="mdi {{ $direction === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down' }}"></i> @endif</a></th>
                        <th><a class="table-sort-btn" data-live-link href="{{ $sortLink('items_count') }}">{{ __('Linked items') }} @if($sort === 'items_count') <i class="mdi {{ $direction === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down' }}"></i> @endif</a></th>
                        <th><a class="table-sort-btn" data-live-link href="{{ $sortLink('is_active') }}">{{ __('Status') }} @if($sort === 'is_active') <i class="mdi {{ $direction === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down' }}"></i> @endif</a></th>
                        <th><a class="table-sort-btn" data-live-link href="{{ $sortLink('updated_at') }}">{{ __('Updated') }} @if($sort === 'updated_at') <i class="mdi {{ $direction === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down' }}"></i> @endif</a></th>
                        <th class="text-end rtl-text-start">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($suppliers as $supplier)
                    <tr>
                        <td>
                            <div class="fw-bold">{{ $supplier->name }}</div>
                            <div class="text-muted small">{{ $supplier->contact_name ?: '—' }}</div>
                        </td>
                        <td>
                            <div class="fw-semibold">{{ $supplier->company ?: '—' }}</div>
                            <div class="text-muted small">{{ $supplier->country ?: '—' }}</div>
                        </td>
                        <td>
                            <div class="small mb-1">{{ $supplier->email ?: '—' }}</div>
                            <div class="small text-muted">{{ $supplier->phone ?: '—' }}</div>
                        </td>
                        <td>
                            <div class="fw-semibold">{{ $supplier->items_count }}</div>
                            <div class="text-muted small">{{ __('Purchases') }}: {{ $supplier->purchases_count }}</div>
                        </td>
                        <td>
                            <span class="badge admin-status-badge {{ $supplier->is_active ? 'badge-soft-success' : 'badge-soft-secondary' }}">{{ $supplier->is_active ? __('Active') : __('Inactive') }}</span>
                        </td>
                        <td>
                            <div class="fw-semibold">{{ optional($supplier->updated_at)->format('d M Y') }}</div>
                            <div class="text-muted small">{{ optional($supplier->updated_at)->format('h:i A') }}</div>
                        </td>
                        <td class="text-end rtl-text-start">
                            <div class="d-inline-flex gap-2 flex-wrap justify-content-end rtl-justify-start">
                                <a href="{{ route('admin.suppliers.edit', $supplier) }}" class="btn-table-icon btn-edit" title="{{ __('Edit Supplier') }}"><i class="mdi mdi-pencil-outline"></i></a>
                                @if($supplier->purchases_count === 0)
                                    <form method="POST" action="{{ route('admin.suppliers.destroy', $supplier) }}" data-confirm-message="{{ __('Delete this supplier?') }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn-table-icon btn-delete" title="{{ __('Delete this supplier?') }}"><i class="mdi mdi-trash-can-outline"></i></button>
                                    </form>
                                @else
                                    <span class="btn-table-icon text-muted" title="{{ __('Suppliers with purchase history are protected from deletion.') }}"><i class="mdi mdi-lock-outline"></i></span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @if($suppliers->hasPages())
            <div class="mt-4 d-flex justify-content-center">{{ $suppliers->links() }}</div>
        @endif
    @else
        <div class="admin-empty-state">
            <div class="admin-empty-icon"><i class="mdi mdi-truck-delivery-outline"></i></div>
            <h4 class="fw-bold mb-2">{{ __('No suppliers found') }}</h4>
            <p class="text-muted mb-3">{{ __('Try clearing the filters or create a supplier for a new sourcing relationship.') }}</p>
            <a href="{{ route('admin.suppliers.create') }}" class="btn btn-primary">{{ __('Add supplier') }}</a>
        </div>
    @endif
</div></div>
</div>
