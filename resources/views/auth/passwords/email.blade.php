@extends('layouts.app')

@section('title', __('Reset password') . ' | ' . ($storeSettings['store_name'] ?? 'Storefront'))

@section('content')
<section class="py-5 lc-page-shell">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-xl-5 col-lg-6">
                <div class="lc-card p-4 p-lg-5">
                    <div class="text-center mb-4">
                        <div class="text-uppercase small text-muted fw-bold">{{ __('Customer account access') }}</div>
                        <h1 class="fw-bold mb-2">{{ __('Reset your password') }}</h1>
                        <p class="text-muted mb-0">{{ __('Enter the email address used for your account and we will send a reset link if it matches an account.') }}</p>
                    </div>

                    <form method="POST" action="{{ route('password.email') }}" class="d-grid gap-3">
                        @csrf
                        <div>
                            <label for="email" class="form-label fw-bold">{{ __('Email address') }}</label>
                            <input id="email" type="email" class="form-control lc-form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required autocomplete="email" autofocus>
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <button type="submit" class="btn lc-btn-primary">{{ __('Send reset link') }}</button>
                        <div class="text-center text-muted">{{ __('Remembered your password?') }} <a href="{{ route('login') }}">{{ __('Back to login') }}</a></div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
