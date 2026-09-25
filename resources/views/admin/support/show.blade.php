@extends('layouts.admin')

@section('title', $supportCase->case_number . ' | ' . __('Customer Support'))

@section('content')
<x-admin.page-header
    :kicker="__('Customer Support')"
    :title="$supportCase->case_number"
    :description="$supportCase->subject"
>
    <a href="{{ route('admin.support.index') }}" class="btn btn-light border">{{ __('Back to support') }}</a>
</x-admin.page-header>

<div class="admin-page-shell">
    <div class="row g-4 align-items-start">
        <div class="col-xl-8">
            <div class="admin-card mb-4">
                <div class="admin-card-body">
                    <div class="d-flex justify-content-between gap-3 flex-wrap align-items-start mb-4">
                        <div>
                            <div class="d-flex gap-2 flex-wrap mb-2">
                                <span class="badge admin-status-badge {{ $supportCase->status_badge_class }}">{{ $supportCase->status_label }}</span>
                                <span class="badge admin-status-badge {{ $supportCase->priority_badge_class }}">{{ $supportCase->priority_label }}</span>
                                @if($supportCase->category)<span class="badge badge-soft-secondary">{{ $supportCase->category }}</span>@endif
                            </div>
                            <h3 class="mb-1">{{ $supportCase->subject }}</h3>
                            <div class="text-muted small">{{ __('Created') }} {{ $supportCase->created_at?->format('Y-m-d H:i') }}</div>
                            <div class="d-flex gap-2 flex-wrap mt-2">
                                <span class="badge admin-status-badge {{ $supportCase->first_response_sla_state === 'breached' ? 'badge-soft-danger' : ($supportCase->first_response_sla_state === 'met' ? 'badge-soft-success' : 'badge-soft-info') }}">
                                    {{ __('First response SLA') }}: {{ __(ucfirst(str_replace('_', ' ', $supportCase->first_response_sla_state))) }}
                                </span>
                                <span class="badge admin-status-badge {{ $supportCase->resolution_sla_state === 'breached' ? 'badge-soft-danger' : ($supportCase->resolution_sla_state === 'met' ? 'badge-soft-success' : 'badge-soft-info') }}">
                                    {{ __('Resolution SLA') }}: {{ __(ucfirst(str_replace('_', ' ', $supportCase->resolution_sla_state))) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="d-grid gap-3">
                        @forelse($supportCase->messages as $message)
                            <div class="rounded-4 border p-3 {{ $message->isInternal() ? 'bg-light-subtle border-warning-subtle' : '' }}">
                                <div class="d-flex justify-content-between gap-2 flex-wrap mb-2">
                                    <div class="fw-semibold">
                                        {{ $message->author?->name ?? __('System') }}
                                        <span class="text-muted small">· {{ $message->author_type === \App\Models\SupportCaseMessage::AUTHOR_CUSTOMER ? __('Customer') : __('Staff') }}</span>
                                    </div>
                                    <div class="d-flex gap-2 align-items-center">
                                        @if($message->isInternal())<span class="badge badge-soft-warning">{{ __('Internal note') }}</span>@endif
                                        <span class="text-muted small">{{ $message->created_at?->format('Y-m-d H:i') }}</span>
                                    </div>
                                </div>
                                <div style="white-space:pre-wrap">{{ $message->body }}</div>
                            </div>
                        @empty
                            <div class="text-muted">{{ __('No messages yet.') }}</div>
                        @endforelse
                    </div>
                </div>
            </div>

            @if(auth()->user()?->hasPermission('support.manage'))
                <div class="admin-card">
                    <div class="admin-card-body">
                        <h4 class="mb-1">{{ __('Add reply or internal note') }}</h4>
                        <p class="text-muted small">{{ __('Customer-visible replies appear in the customer portal. Internal notes are visible only to staff.') }}</p>
                        <form method="POST" action="{{ route('admin.support.reply', $supportCase) }}" data-submit-loading>
                            @csrf
                            <div class="row g-3">
                                @if($replyTemplates->isNotEmpty())
                                <div class="col-md-8">
                                    <label class="form-label fw-semibold">{{ __('Reply template') }}</label>
                                    <select class="form-select" data-support-template>
                                        <option value="">{{ __('Write without a template') }}</option>
                                        @foreach($replyTemplates as $template)
                                            <option value="{{ $template->id }}">{{ $template->displayName() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                @endif
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">{{ __('Visibility') }}</label>
                                    <select name="visibility" class="form-select" data-support-visibility>
                                        <option value="{{ \App\Models\SupportCaseMessage::VISIBILITY_CUSTOMER }}">{{ __('Customer-visible reply') }}</option>
                                        <option value="{{ \App\Models\SupportCaseMessage::VISIBILITY_INTERNAL }}">{{ __('Internal note') }}</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <textarea name="message" rows="6" class="form-control @error('message') is-invalid @enderror" maxlength="5000" required data-support-message>{{ old('message') }}</textarea>
                                    @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-12 text-end">
                                    <button class="btn btn-primary" data-loading-text="{{ __('Saving message...') }}">{{ __('Add message') }}</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-xl-4">
            <div class="admin-card mb-4">
                <div class="admin-card-body">
                    <h4 class="mb-3">{{ __('Commerce context') }}</h4>

                    <div class="small text-muted">{{ __('Customer') }}</div>
                    <div class="fw-semibold">{{ $commerceContext['customer']['name'] ?? __('Guest / unlinked') }}</div>
                    @if(!empty($commerceContext['customer']['email']))
                        <div class="text-muted small">{{ $commerceContext['customer']['email'] }}</div>
                    @endif
                    @if(!empty($commerceContext['customer']['statement_url']))
                        <div class="mt-2 mb-3"><a href="{{ $commerceContext['customer']['statement_url'] }}" class="btn btn-sm btn-outline-secondary">{{ __('Open customer statement') }}</a></div>
                    @else
                        <div class="mb-3"></div>
                    @endif

                    <div class="small text-muted">{{ __('Order') }}</div>
                    @if($commerceContext['order'])
                        <div class="d-flex justify-content-between gap-2 align-items-start">
                            <div>
                                <div class="fw-semibold">{{ $commerceContext['order']['number'] }}</div>
                                <div class="text-muted small">
                                    {{ $commerceContext['order']['status_label'] }} ·
                                    {{ $commerceContext['order']['payment_status_label'] }} ·
                                    {{ $commerceContext['order']['delivery_status_label'] }}
                                </div>
                                <div class="text-muted small">{{ __('Order value') }}: {{ $commerceContext['order']['currency'] }} {{ number_format((float) $commerceContext['order']['grand_total'], 2) }}</div>
                            </div>
                            @if($commerceContext['order']['url'])
                                <a href="{{ $commerceContext['order']['url'] }}" class="btn btn-sm btn-outline-primary">{{ __('Open order') }}</a>
                            @endif
                        </div>
                    @else
                        <div class="text-muted mb-3">{{ __('No order linked') }}</div>
                    @endif

                    @if($commerceContext['delivery'])
                        <hr>
                        <div class="small text-muted mb-1">{{ __('Delivery context') }}</div>
                        <div class="fw-semibold">{{ $commerceContext['delivery']['status_label'] }} · {{ $commerceContext['delivery']['method_label'] }}</div>
                        @if($commerceContext['delivery']['provider'])
                            <div class="text-muted small">{{ __('Provider') }}: {{ $commerceContext['delivery']['provider'] }}</div>
                        @endif
                        @if($commerceContext['delivery']['tracking_number'])
                            <div class="text-muted small">{{ __('Tracking number') }}: {{ $commerceContext['delivery']['tracking_number'] }}</div>
                        @endif
                        @if($commerceContext['delivery']['estimated_delivery_date'])
                            <div class="text-muted small">{{ __('Estimated delivery date') }}: {{ $commerceContext['delivery']['estimated_delivery_date'] }}</div>
                        @endif
                        @if($commerceContext['delivery']['url'])
                            <div class="mt-2"><a href="{{ $commerceContext['delivery']['url'] }}" class="btn btn-sm btn-outline-secondary">{{ __('Open delivery workspace') }}</a></div>
                        @endif
                    @endif

                    @if($commerceContext['payment_visible'])
                        <hr>
                        <div class="small text-muted mb-1">{{ __('Latest payment') }}</div>
                        @if($commerceContext['payment'])
                            <div class="d-flex justify-content-between gap-2 align-items-start">
                                <div>
                                    <span class="badge admin-status-badge {{ $commerceContext['payment']['status_badge_class'] }}">{{ $commerceContext['payment']['status_label'] }}</span>
                                    <div class="mt-1 fw-semibold">{{ $commerceContext['payment']['currency'] }} {{ number_format((float) $commerceContext['payment']['amount'], 2) }}</div>
                                    <div class="text-muted small">{{ $commerceContext['payment']['method_label'] }}</div>
                                    @if($commerceContext['payment']['reference'])
                                        <div class="text-muted small">{{ __('Payment reference') }}: {{ $commerceContext['payment']['reference'] }}</div>
                                    @endif
                                </div>
                                <a href="{{ $commerceContext['payment']['url'] }}" class="btn btn-sm btn-outline-primary">{{ __('Open payment') }}</a>
                            </div>
                        @else
                            <div class="text-muted small">{{ __('No payment record linked to this order.') }}</div>
                        @endif
                    @endif

                    @if($commerceContext['returns_visible'] && $commerceContext['order'])
                        <hr>
                        <div class="small text-muted mb-2">{{ __('Return requests') }}</div>
                        @if($commerceContext['returns']->isEmpty())
                            <div class="text-muted small">{{ __('No return requests linked to this order.') }}</div>
                        @else
                            <div class="d-grid gap-2">
                                @foreach($commerceContext['returns'] as $returnContext)
                                    <a href="{{ $returnContext['url'] }}" class="text-decoration-none d-flex justify-content-between gap-2 align-items-center border rounded-3 p-2">
                                        <span>
                                            <strong>{{ $returnContext['reference'] }}</strong>
                                            @if($returnContext['requested_at'])<span class="text-muted small d-block">{{ $returnContext['requested_at'] }}</span>@endif
                                        </span>
                                        <span class="badge admin-status-badge {{ $returnContext['status_badge_class'] }}">{{ $returnContext['status_label'] }}</span>
                                    </a>
                                @endforeach
                            </div>
                            @if($commerceContext['return_total'] > $commerceContext['returns']->count())
                                <div class="text-muted small mt-2">{{ __('Showing latest :shown of :total return requests.', ['shown' => $commerceContext['returns']->count(), 'total' => $commerceContext['return_total']]) }}</div>
                            @endif
                        @endif
                    @endif

                    <hr>
                    <div class="small text-muted">{{ __('First staff response') }}</div>
                    <div class="mb-3">{{ $supportCase->first_response_at?->format('Y-m-d H:i') ?? __('Not yet') }}</div>
                    <div class="small text-muted">{{ __('First response due') }}</div>
                    <div class="mb-3">{{ $supportCase->first_response_due_at?->format('Y-m-d H:i') ?? '—' }}</div>
                    <div class="small text-muted">{{ __('Resolution due') }}</div>
                    <div>{{ $supportCase->resolution_due_at?->format('Y-m-d H:i') ?? '—' }}</div>
                </div>
            </div>

            @if(auth()->user()?->hasPermission('support.manage'))
                <div class="admin-card">
                    <div class="admin-card-body">
                        <h4 class="mb-3">{{ __('Ownership & workflow') }}</h4>
                        <form method="POST" action="{{ route('admin.support.update', $supportCase) }}" data-submit-loading>
                            @csrf @method('PATCH')
                            <div class="mb-3">
                                <label class="form-label fw-semibold">{{ __('Owner') }}</label>
                                <select name="assigned_to_user_id" class="form-select">
                                    <option value="">{{ __('Unassigned') }}</option>
                                    @foreach($staff as $member)
                                        <option value="{{ $member->id }}" @selected((int)$supportCase->assigned_to_user_id === (int)$member->id)>{{ $member->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">{{ __('Priority') }}</label>
                                <select name="priority" class="form-select">
                                    @foreach(\App\Models\SupportCase::priorityOptions() as $value => $label)
                                        <option value="{{ $value }}" @selected($supportCase->priority === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">{{ __('Status') }}</label>
                                <select name="status" class="form-select">
                                    @foreach(\App\Models\SupportCase::statusOptions() as $value => $label)
                                        <option value="{{ $value }}" @selected($supportCase->status === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <button class="btn btn-primary w-100" data-loading-text="{{ __('Saving case...') }}">{{ __('Save case') }}</button>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection


@if(auth()->user()?->hasPermission('support.manage') && isset($replyTemplates) && $replyTemplates->isNotEmpty())
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const select = document.querySelector('[data-support-template]');
    const message = document.querySelector('[data-support-message]');
    const visibility = document.querySelector('[data-support-visibility]');
    const templates = @json($replyTemplatePayload);

    if (!select || !message) return;

    select.addEventListener('change', function () {
        const template = templates[this.value];
        if (!template) return;
        message.value = template.body || '';
        if (visibility && template.visibility) visibility.value = template.visibility;
        message.focus();
    });
});
</script>
@endpush
@endif
