@extends('layouts.app')

@section('title', __('Create new password') . ' | ' . ($storeSettings['store_name'] ?? 'Storefront'))

@section('content')
<section class="py-5 lc-page-shell">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-xl-5 col-lg-6">
                <div class="lc-card p-4 p-lg-5">
                    <div class="text-center mb-4">
                        <div class="text-uppercase small text-muted fw-bold">{{ __('Customer account access') }}</div>
                        <h1 class="fw-bold mb-2">{{ __('Create a new password') }}</h1>
                        <p class="text-muted mb-0">{{ __('Choose a new password for your account, then sign in with your updated credentials.') }}</p>
                    </div>

                    <form method="POST" action="{{ route('password.update') }}" class="d-grid gap-3">
                        @csrf
                        <input type="hidden" name="token" value="{{ $token }}">

                        <div>
                            <label for="email" class="form-label fw-bold">{{ __('Email address') }}</label>
                            <input id="email" type="email" class="form-control lc-form-control @error('email') is-invalid @enderror" name="email" value="{{ $email ?? old('email') }}" required autocomplete="email" autofocus>
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div>
                            <label for="password" class="form-label fw-bold">{{ __('New password') }}</label>
                            <input id="password" type="password" class="form-control lc-form-control @error('password') is-invalid @enderror" name="password" required autocomplete="new-password">
                            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div>
                            <label for="password-confirm" class="form-label fw-bold">{{ __('Confirm new password') }}</label>
                            <input id="password-confirm" type="password" class="form-control lc-form-control" name="password_confirmation" required autocomplete="new-password">
                        </div>

                        <button type="submit" class="btn lc-btn-primary">{{ __('Save new password') }}</button>
                        <div class="text-center text-muted"><a href="{{ route('login') }}">{{ __('Back to login') }}</a></div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
