<nav class="lc-account-nav mb-4" aria-label="{{ __('Account navigation') }}">
    <a href="{{ route('account.index') }}" @if(request()->routeIs('account.index')) aria-current="page" @endif>{{ __('Overview') }}</a>
    <a href="{{ route('orders.index') }}" @if(request()->routeIs('orders.*')) aria-current="page" @endif>{{ __('My Orders') }}</a>
    <a href="{{ route('returns.index') }}" @if(request()->routeIs('returns.*')) aria-current="page" @endif>{{ __('My Returns') }}</a>
    <a href="{{ route('support.index') }}" @if(request()->routeIs('support.*')) aria-current="page" @endif>{{ __('Help & Support') }}</a>
    <a href="{{ route('account.addresses.index') }}" @if(request()->routeIs('account.addresses.*')) aria-current="page" @endif>{{ __('Address book') }}</a>
    <a href="{{ route('notifications.index') }}" @if(request()->routeIs('notifications.*')) aria-current="page" @endif>{{ __('Notifications') }}</a>
</nav>
