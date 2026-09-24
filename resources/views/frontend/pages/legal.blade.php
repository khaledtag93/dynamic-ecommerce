@extends('layouts.app')

@section('title', ($title ?: __('Store policy')) . ' | ' . ($storeSettings['store_name'] ?? 'Store'))

@section('content')
<section class="py-5 lc-page-shell">
    <div class="container" style="max-width: 920px;">
        <div class="mb-4">
            <div class="text-uppercase small text-muted fw-bold">{{ __('Policy') }}</div>
            <h1 class="lc-section-title mb-2">{{ $title }}</h1>
            @if(!empty($intro))
                <p class="text-muted mb-0">{{ $intro }}</p>
            @endif
        </div>
        <div class="lc-card p-4 p-lg-5">
            @if(!empty(trim(strip_tags((string) $body))))
                <div class="lc-legal-content">{!! $body !!}</div>
            @else
                <div class="lc-empty-state py-4">
                    <div class="lc-empty-icon"><i class="bi bi-file-earmark-text"></i></div>
                    <h2 class="h4 fw-bold mb-2">{{ __('This policy has not been published yet') }}</h2>
                    <p class="text-muted mb-4">{{ __('Please contact support if you need policy information before placing an order.') }}</p>
                    <a href="{{ route('frontend.contact') }}" class="btn lc-btn-primary">{{ __('Contact support') }}</a>
                </div>
            @endif
        </div>
    </div>
</section>
@endsection
