@extends('layouts.app')

@section('title', __('Help & Support') . ' | ' . ($storeSettings['store_name'] ?? 'Storefront'))

@section('content')
<section class="py-5 lc-page-shell">
    <div class="container">
        <x-frontend.page-hero :eyebrow="__('Account')" :title="__('Help & Support')" :description="__('Open a support request and keep every reply connected to the same case.')" class="mb-4" />
        @include('frontend.account.partials.navigation')

        <div class="d-flex justify-content-end mb-3">
            <a href="{{ route('support.create') }}" class="btn lc-btn-primary"><i class="bi bi-plus-lg me-2"></i>{{ __('New support request') }}</a>
        </div>

        <div class="lc-card overflow-hidden">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>{{ __('Case') }}</th><th>{{ __('Order') }}</th><th>{{ __('Priority') }}</th><th>{{ __('Status') }}</th><th>{{ __('Updated') }}</th><th></th></tr></thead>
                    <tbody>
                        @forelse($cases as $supportCase)
                            <tr>
                                <td><div class="fw-bold">{{ $supportCase->case_number }}</div><div class="text-muted small">{{ \Illuminate\Support\Str::limit($supportCase->subject, 70) }}</div></td>
                                <td>{{ $supportCase->order?->order_number ?? '—' }}</td>
                                <td>{{ $supportCase->priority_label }}</td>
                                <td><span class="lc-status-badge {{ in_array($supportCase->status, [\App\Models\SupportCase::STATUS_RESOLVED, \App\Models\SupportCase::STATUS_CLOSED], true) ? 'lc-badge-success' : 'lc-badge-processing' }}">{{ $supportCase->status_label }}</span></td>
                                <td>{{ $supportCase->updated_at?->format('d M Y H:i') }}</td>
                                <td class="text-end"><a href="{{ route('support.show', $supportCase) }}" class="btn lc-btn-soft btn-sm">{{ __('Open') }}</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-5">{{ __('You have no support requests yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($cases->hasPages())<div class="p-3 border-top">{{ $cases->links() }}</div>@endif
        </div>
    </div>
</section>
@endsection
