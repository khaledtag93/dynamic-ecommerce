<nav class="lc-account-nav mb-4" aria-label="{{ __('Account navigation') }}">
    <a href="{{ route('account.index') }}" aria-current="{{ request()->routeIs('account.index') ? 'page' : 'false' }}">{{ __('Overview') }}</a>
    <a href="{{ route('orders.index') }}" aria-current="{{ request()->routeIs('orders.*') ? 'page' : 'false' }}">{{ __('My Orders') }}</a>
    <a href="{{ route('returns.index') }}" aria-current="{{ request()->routeIs('returns.*') ? 'page' : 'false' }}">{{ __('My Returns') }}</a>
    <a href="{{ route('support.index') }}" aria-current="{{ request()->routeIs('support.*') ? 'page' : 'false' }}">{{ __('Help & Support') }}</a>
    <a href="{{ route('account.addresses.index') }}" aria-current="{{ request()->routeIs('account.addresses.*') ? 'page' : 'false' }}">{{ __('Address book') }}</a>
    <a href="{{ route('notifications.index') }}" aria-current="{{ request()->routeIs('notifications.*') ? 'page' : 'false' }}">{{ __('Notifications') }}</a>
</nav>
