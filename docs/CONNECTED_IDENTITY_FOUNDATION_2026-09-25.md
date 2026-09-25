# Connected Identity Foundation — 2026-09-25

## Scope

This slice prepares Dynamic for customer sign-in through external identity providers without weakening account ownership rules.

Implemented:
- provider-neutral `connected_identities` table;
- Google and Facebook provider identifiers reserved for the first integration wave;
- one identity per provider per user;
- one provider identity cannot be claimed by multiple users;
- verified provider email is required before linking;
- provider email must match the currently signed-in Dynamic account for explicit linking;
- an existing Dynamic email cannot be silently merged during a future provider callback;
- existing linked identities can resolve the owning account and record last-login time;
- focused regression coverage for collision, email verification, email mismatch, and silent-merge prevention.

## Deliberately not implemented yet

OAuth redirects/callbacks are not wired in this slice because the current Laravel 10 project does not yet include `laravel/socialite` in its Composer lock. Adding only `composer.json` from GitHub would leave the dependency graph inconsistent and break CI/deployment.

The next integration slice should:
1. add Laravel Socialite with a real Composer lock update;
2. configure Google and Facebook credentials through environment-backed `config/services.php`;
3. add redirect and callback routes;
4. map provider responses into `ConnectedIdentityService`;
5. expose explicit Link / Unlink controls inside the authenticated customer account;
6. keep login/password recovery available so customers are never trapped behind one provider;
7. complete EN/AR/RTL UI and provider-error handling;
8. verify callback state/session protection in QAS.

## Security rule

Never merge an external identity into an existing Dynamic account only because the provider returned the same email. Existing-email customers must first authenticate to Dynamic, then explicitly link the provider from account settings.
