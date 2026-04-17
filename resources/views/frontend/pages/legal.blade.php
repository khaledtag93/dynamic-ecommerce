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
            <div class="lc-legal-content">{!! $body !!}</div>
        </div>
    </div>
</section>
@endsection
