@extends('layouts.admin')

@section('title', __('Request attendance correction') . ' | Admin')

@section('content')
<div class="admin-page-shell">
    <x-admin.page-header :kicker="__('Workforce')" :title="__('Request attendance correction')" :description="__('Request a reviewed correction while preserving the original clock history.')">
        <a href="{{ route('admin.workforce.time-clock') }}" class="btn btn-light border btn-text-icon"><i class="mdi mdi-arrow-left"></i><span>{{ __('Back to time clock') }}</span></a>
    </x-admin.page-header>

    <div class="row g-4">
        <div class="col-xl-5">
            <div class="admin-card">
                <div class="admin-card-body">
                    <div class="admin-inline-label">{{ __('Recorded attendance') }}</div>
                    <h4 class="mb-3">{{ $session->clock_in_at->format('d M Y') }}</h4>

                    <div class="mb-3">
                        <div class="text-muted small">{{ __('Recorded clock-in') }}</div>
                        <div class="fw-semibold">{{ $session->clock_in_at->format('d M Y H:i') }}</div>
                    </div>
                    <div class="mb-3">
                        <div class="text-muted small">{{ __('Recorded clock-out') }}</div>
                        <div class="fw-semibold">{{ $session->clock_out_at?->format('d M Y H:i') ?: '—' }}</div>
                    </div>

                    @if($session->hasApprovedCorrection())
                        <hr>
                        <div class="admin-inline-label">{{ __('Current effective attendance') }}</div>
                        <div class="fw-semibold">{{ $session->effectiveClockInAt()->format('d M Y H:i') }} → {{ $session->effectiveClockOutAt()?->format('d M Y H:i') }}</div>
                        <div class="section-note">{{ __('The original record above remains unchanged.') }}</div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-xl-7">
            <div class="admin-card">
                <div class="admin-card-body">
                    @error('correction')<div class="alert alert-danger border-0 rounded-4">{{ $message }}</div>@enderror

                    @if($session->pendingCorrection)
                        <div class="admin-empty-state py-4">
                            <div class="empty-icon"><i class="mdi mdi-clock-alert-outline"></i></div>
                            <h4 class="mb-2">{{ __('Correction already pending') }}</h4>
                            <p class="text-muted mb-0">{{ __('A manager must review the current request before another correction can be submitted for this session.') }}</p>
                        </div>
                    @else
                        <form method="POST" action="{{ route('admin.workforce.corrections.store', $session) }}" data-submit-loading>
                            @csrf
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">{{ __('Corrected clock-in') }}</label>
                                    <input type="datetime-local" name="requested_clock_in_at" class="form-control @error('requested_clock_in_at') is-invalid @enderror"
                                           value="{{ old('requested_clock_in_at', $session->effectiveClockInAt()->format('Y-m-d\TH:i')) }}" required>
                                    @error('requested_clock_in_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">{{ __('Corrected clock-out') }}</label>
                                    <input type="datetime-local" name="requested_clock_out_at" class="form-control @error('requested_clock_out_at') is-invalid @enderror"
                                           value="{{ old('requested_clock_out_at', $session->effectiveClockOutAt()?->format('Y-m-d\TH:i')) }}" required>
                                    @error('requested_clock_out_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold">{{ __('Reason') }}</label>
                                    <textarea name="reason" rows="5" maxlength="2000" class="form-control @error('reason') is-invalid @enderror" required placeholder="{{ __('Explain what is wrong and why the correction is needed') }}">{{ old('reason') }}</textarea>
                                    @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>

                            <div class="alert alert-info border-0 rounded-4 mt-4 mb-0">
                                {{ __('Submitting a request does not change attendance immediately. A manager must approve it first.') }}
                            </div>

                            <div class="admin-form-actions mt-4">
                                <button class="btn btn-primary btn-text-icon" data-loading-text="{{ __('Submitting...') }}"><i class="mdi mdi-send-outline"></i><span>{{ __('Submit correction request') }}</span></button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
