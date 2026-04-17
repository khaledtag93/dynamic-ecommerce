@extends('layouts.app')

@section('title', __('Create account') . ' | ' . ($storeSettings['store_name'] ?? 'Storefront'))

@section('content')
<section class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-5">
                <div class="lc-card p-4 p-lg-5">
                    <div class="text-center mb-4">
                        <div class="text-uppercase small text-muted fw-bold">{{ __('Create account') }}</div>
                        <h1 class="fw-bold mb-2">{{ __('Join :store', ['store' => $storeSettings['store_name'] ?? __('our store')]) }}</h1>
                        <p class="text-muted mb-0">{{ __('Save your orders and continue smoothly through checkout.') }}</p>
                    </div>
                    <form method="POST" action="{{ route('register') }}" class="d-grid gap-3">
                        @csrf
                        <div>
                            <label class="form-label fw-bold">{{ __('Full name') }}</label>
                            <input id="name" type="text" class="form-control lc-form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name') }}" required autocomplete="name" autofocus>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label class="form-label fw-bold">{{ __('Email address') }}</label>
                            <input id="email" type="email" class="form-control lc-form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required autocomplete="email">
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label class="form-label fw-bold">{{ __('Password') }}</label>
                            <input id="password" type="password" class="form-control lc-form-control @error('password') is-invalid @enderror" name="password" required autocomplete="new-password">
                            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label class="form-label fw-bold">{{ __('Confirm password') }}</label>
                            <input id="password-confirm" type="password" class="form-control lc-form-control" name="password_confirmation" required autocomplete="new-password">
                        </div>
                        <button type="submit" class="btn lc-btn-primary">{{ __('Create account') }}</button>
                        <div class="text-center text-muted">{{ __('Already have an account?') }} <a href="{{ route('login') }}">{{ __('Login') }}</a></div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
