@extends('layouts.app')

@section('title', __('Login') . ' | ' . ($storeSettings['store_name'] ?? 'Storefront'))

@section('content')
<section class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-5">
                <div class="lc-card p-4 p-lg-5">
                    <div class="text-center mb-4">
                        <div class="text-uppercase small text-muted fw-bold">{{ __('Welcome back') }}</div>
                        <h1 class="fw-bold mb-2">{{ __('Login to your account') }}</h1>
                        <p class="text-muted mb-0">{{ __('Continue to cart, checkout, and orders.') }}</p>
                    </div>
                    <form method="POST" action="{{ route('login') }}" class="d-grid gap-3">
                        @csrf
                        <div>
                            <label class="form-label fw-bold">{{ __('Email address') }}</label>
                            <input id="email" type="email" class="form-control lc-form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required autocomplete="email" autofocus>
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label class="form-label fw-bold">{{ __('Password') }}</label>
                            <input id="password" type="password" class="form-control lc-form-control @error('password') is-invalid @enderror" name="password" required autocomplete="current-password">
                            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
                                <label class="form-check-label" for="remember">{{ __('Remember me') }}</label>
                            </div>
                            @if (Route::has('password.request'))
                                <a href="{{ route('password.request') }}">{{ __('Forgot password?') }}</a>
                            @endif
                        </div>
                        <button type="submit" class="btn lc-btn-primary">{{ __('Login') }}</button>
                        <div class="text-center text-muted">{{ __('New here?') }} <a href="{{ route('register') }}">{{ __('Create an account') }}</a></div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
