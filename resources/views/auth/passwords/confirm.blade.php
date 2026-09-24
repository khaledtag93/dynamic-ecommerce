@extends('layouts.app')

@section('title', __('Confirm password') . ' | ' . ($storeSettings['store_name'] ?? 'Storefront'))

@section('content')
<section class="py-5 lc-page-shell">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-xl-5 col-lg-6">
                <div class="lc-card p-4 p-lg-5">
                    <div class="text-center mb-4">
                        <div class="text-uppercase small text-muted fw-bold">{{ __('Account security') }}</div>
                        <h1 class="fw-bold mb-2">{{ __('Confirm your password') }}</h1>
                        <p class="text-muted mb-0">{{ __('For your security, enter your password again before continuing.') }}</p>
                    </div>

                    <form method="POST" action="{{ route('password.confirm') }}" class="d-grid gap-3">
                        @csrf
                        <div>
                            <label for="password" class="form-label fw-bold">{{ __('Password') }}</label>
                            <input id="password" type="password" class="form-control lc-form-control @error('password') is-invalid @enderror" name="password" required autocomplete="current-password">
                            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <button type="submit" class="btn lc-btn-primary">{{ __('Continue securely') }}</button>

                        @if (Route::has('password.request'))
                            <div class="text-center"><a href="{{ route('password.request') }}">{{ __('Forgot password?') }}</a></div>
                        @endif
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
