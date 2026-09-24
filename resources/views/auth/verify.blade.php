@extends('layouts.app')

@section('title', __('Verify email') . ' | ' . ($storeSettings['store_name'] ?? 'Storefront'))

@section('content')
<section class="py-5 lc-page-shell">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-xl-5 col-lg-6">
                <div class="lc-card p-4 p-lg-5">
                    <div class="text-center mb-4">
                        <div class="text-uppercase small text-muted fw-bold">{{ __('Customer account access') }}</div>
                        <h1 class="fw-bold mb-2">{{ __('Verify your email') }}</h1>
                        <p class="text-muted mb-0">{{ __('Check your inbox and use the verification link to continue to your account.') }}</p>
                    </div>

                    @if (session('resent'))
                        <div class="alert alert-success border-0 rounded-4" role="alert">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </div>
                    @endif

                    <div class="d-grid gap-3">
                        <p class="text-muted mb-0">{{ __('Did not receive the email? You can request another verification link.') }}</p>
                        <form method="POST" action="{{ route('verification.resend') }}">
                            @csrf
                            <button type="submit" class="btn lc-btn-primary w-100">{{ __('Send another verification link') }}</button>
                        </form>
                        <a href="{{ route('frontend.home') }}" class="btn lc-btn-soft">{{ __('Back to store') }}</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
