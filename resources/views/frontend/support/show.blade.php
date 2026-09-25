@extends('layouts.app')

@section('title', $supportCase->case_number . ' | ' . __('Help & Support'))

@section('content')
<section class="py-5 lc-page-shell">
    <div class="container">
        <x-frontend.page-hero :eyebrow="__('Help & Support')" :title="$supportCase->case_number" :description="$supportCase->subject" class="mb-4" />
        @include('frontend.account.partials.navigation')

        <div class="row g-4 align-items-start">
            <div class="col-lg-8">
                <div class="lc-card p-4 mb-4">
                    <div class="d-flex gap-2 flex-wrap mb-4">
                        <span class="lc-status-badge {{ in_array($supportCase->status, [\App\Models\SupportCase::STATUS_RESOLVED, \App\Models\SupportCase::STATUS_CLOSED], true) ? 'lc-badge-success' : 'lc-badge-processing' }}" data-support-case-status>{{ $supportCase->status_label }}</span>
                        <span class="badge text-bg-light">{{ $supportCase->priority_label }}</span>
                    </div>
                    <div class="d-grid gap-3" data-support-message-list>
                        @foreach($supportCase->customerMessages as $message)
                            <div class="border rounded-4 p-3">
                                <div class="d-flex justify-content-between gap-2 flex-wrap mb-2">
                                    <div class="fw-bold">{{ $message->author_type === \App\Models\SupportCaseMessage::AUTHOR_CUSTOMER ? __('You') : ($message->author?->name ?? __('Support team')) }}</div>
                                    <div class="text-muted small">{{ $message->created_at?->format('d M Y H:i') }}</div>
                                </div>
                                <div style="white-space:pre-wrap">{{ $message->body }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>

                @if($supportCase->status !== \App\Models\SupportCase::STATUS_CLOSED)
                    <div class="lc-card p-4">
                        <h2 class="h5 fw-bold mb-3">{{ __('Add a reply') }}</h2>
                        <form method="POST" action="{{ route('support.reply', $supportCase) }}" data-support-reply-form>
                            @csrf
                            <textarea name="message" rows="5" class="form-control lc-form-control @error('message') is-invalid @enderror" maxlength="5000" required data-support-reply-input>{{ old('message') }}</textarea>
                            @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="text-end mt-3"><button type="submit" class="btn lc-btn-primary" data-support-reply-submit data-loading-text="{{ __('Sending reply...') }}">{{ __('Send reply') }}</button></div>
                        </form>
                        <div class="small mt-2 d-none" role="status" aria-live="polite" data-support-reply-status></div>
                    </div>
                @else
                    <div class="alert alert-secondary border-0 rounded-4">{{ __('This support case is closed. Open a new request if you need more help.') }}</div>
                @endif
            </div>

            <div class="col-lg-4">
                <div class="lc-card p-4">
                    <h2 class="h5 fw-bold mb-3">{{ __('Case details') }}</h2>
                    <div class="text-muted small">{{ __('Order') }}</div>
                    <div class="fw-semibold mb-3">{{ $supportCase->order?->order_number ?? __('Not linked to an order') }}</div>
                    <div class="text-muted small">{{ __('Created') }}</div>
                    <div class="mb-3">{{ $supportCase->created_at?->format('d M Y H:i') }}</div>
                    <div class="text-muted small">{{ __('Last updated') }}</div>
                    <div data-support-last-updated>{{ $supportCase->updated_at?->format('d M Y H:i') }}</div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('[data-support-reply-form]');
    if (!form || typeof window.fetch !== 'function') return;

    const input = form.querySelector('[data-support-reply-input]');
    const submit = form.querySelector('[data-support-reply-submit]');
    const status = document.querySelector('[data-support-reply-status]');
    const list = document.querySelector('[data-support-message-list]');
    const caseStatus = document.querySelector('[data-support-case-status]');
    const lastUpdated = document.querySelector('[data-support-last-updated]');
    const terminalStatuses = @json([\App\Models\SupportCase::STATUS_RESOLVED, \App\Models\SupportCase::STATUS_CLOSED]);

    const setStatus = (message, isError = false) => {
        if (!status) return;
        status.textContent = message || '';
        status.classList.toggle('text-danger', isError);
        status.classList.toggle('text-success', !isError && Boolean(message));
        status.classList.toggle('d-none', !message);
    };

    const appendReply = (reply) => {
        if (!list) return;

        const card = document.createElement('div');
        card.className = 'border rounded-4 p-3';
        card.dataset.supportMessage = String(reply.id || '');

        const header = document.createElement('div');
        header.className = 'd-flex justify-content-between gap-2 flex-wrap mb-2';

        const author = document.createElement('div');
        author.className = 'fw-bold';
        author.textContent = reply.author_label || @json(__('You'));

        const time = document.createElement('div');
        time.className = 'text-muted small';
        time.textContent = reply.created_at || '';

        const body = document.createElement('div');
        body.style.whiteSpace = 'pre-wrap';
        body.textContent = reply.body || '';

        header.append(author, time);
        card.append(header, body);
        list.appendChild(card);
    };

    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        if (form.dataset.pending === '1') return;

        form.dataset.pending = '1';
        if (submit) {
            submit.disabled = true;
            submit.dataset.originalText = submit.textContent;
            submit.textContent = submit.dataset.loadingText || @json(__('Sending reply...'));
        }
        setStatus('');

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Support-Live': '1',
                },
                body: new FormData(form),
            });

            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                const errors = payload.errors || {};
                const firstError = Object.values(errors).flat()[0];
                throw new Error(firstError || payload.message || @json(__('Could not send your reply. Please try again.')));
            }

            appendReply(payload.reply || {});
            if (input) input.value = '';

            if (caseStatus && payload.case) {
                caseStatus.textContent = payload.case.status_label || payload.case.status || '';
                const terminal = terminalStatuses.includes(payload.case.status);
                caseStatus.classList.toggle('lc-badge-success', terminal);
                caseStatus.classList.toggle('lc-badge-processing', !terminal);
            }

            if (lastUpdated && payload.case?.updated_at) {
                lastUpdated.textContent = payload.case.updated_at;
            }

            setStatus(payload.message || @json(__('Your reply was added.')));
        } catch (error) {
            setStatus(error.message || @json(__('Could not send your reply. Please try again.')), true);
        } finally {
            if (submit) {
                submit.disabled = false;
                submit.textContent = submit.dataset.originalText || @json(__('Send reply'));
                delete submit.dataset.originalText;
            }
            delete form.dataset.pending;
        }
    });
});
</script>
@endpush

