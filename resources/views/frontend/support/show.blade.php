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
                        <span class="lc-status-badge {{ in_array($supportCase->status, [\App\Models\SupportCase::STATUS_RESOLVED, \App\Models\SupportCase::STATUS_CLOSED], true) ? 'lc-badge-success' : 'lc-badge-processing' }}">{{ $supportCase->status_label }}</span>
                        <span class="badge text-bg-light">{{ $supportCase->priority_label }}</span>
                    </div>
                    <div class="d-grid gap-3">
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
                        <form method="POST" action="{{ route('support.reply', $supportCase) }}" data-submit-loading>
                            @csrf
                            <textarea name="message" rows="5" class="form-control lc-form-control @error('message') is-invalid @enderror" maxlength="5000" required>{{ old('message') }}</textarea>
                            @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="text-end mt-3"><button class="btn lc-btn-primary" data-loading-text="{{ __('Sending reply...') }}">{{ __('Send reply') }}</button></div>
                        </form>
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
                    <div>{{ $supportCase->updated_at?->format('d M Y H:i') }}</div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
