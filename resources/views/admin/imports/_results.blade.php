<div data-live-results aria-busy="false">
<div class="admin-card">
    <div class="admin-card-body">
        <div class="admin-table-toolbar">
            <div>
                <h4 class="mb-1">{{ __('Import drafts') }}</h4>
                <div class="text-muted small">{{ __('Showing :count draft(s) on this page.', ['count' => $jobs->count()]) }}</div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table admin-table align-middle mb-0">
                <thead>
                    <tr><th>{{ __('Type') }}</th><th>{{ __('File') }}</th><th>{{ __('Status') }}</th><th>{{ __('Rows') }}</th><th>{{ __('Created') }}</th></tr>
                </thead>
                <tbody>
                    @forelse($jobs as $job)
                        <tr>
                            <td>{{ ucfirst((string) $job->type) }}</td>
                            <td>{{ $job->file_name ?: '—' }}</td>
                            <td><span class="badge admin-status-badge badge-soft-secondary">{{ ucfirst((string) ($job->status ?: 'draft')) }}</span></td>
                            <td>{{ (int) $job->rows_processed }}/{{ (int) $job->rows_total }}</td>
                            <td>{{ optional($job->created_at)->format('M d, Y H:i') ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-5"><div class="admin-empty-state py-4"><div class="empty-icon"><i class="mdi mdi-database-off-outline"></i></div><h5 class="mb-2">{{ __('No import jobs yet') }}</h5><p class="text-muted mb-0">{{ __('Create a draft on the left to prepare future product, customer, or order imports.') }}</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@if($jobs->hasPages())<div class="mt-4">{{ $jobs->links() }}</div>@endif
</div>