<?php

namespace Tests\Feature;

use Tests\TestCase;

class LocaleRedirectSecurityTest extends TestCase
{
    public function test_locale_switch_accepts_safe_local_path_redirect(): void
    {
        $response = $this
            ->from('/cart')
            ->get(route('locale.switch', [
                'locale' => 'ar',
                'redirect' => '/search?q=bag',
            ]));

        $response
            ->assertRedirect(url('/search?q=bag'))
            ->assertSessionHas('locale', 'ar');
    }

    public function test_locale_switch_accepts_same_origin_absolute_redirect(): void
    {
        $target = url('/products/example?source=language');

        $response = $this
            ->from('/cart')
            ->get(route('locale.switch', [
                'locale' => 'en',
                'redirect' => $target,
            ]));

        $response
            ->assertRedirect($target)
            ->assertSessionHas('locale', 'en');
    }

    public function test_locale_switch_rejects_external_absolute_redirect(): void
    {
        $response = $this
            ->from('/cart')
            ->get(route('locale.switch', [
                'locale' => 'ar',
                'redirect' => 'https://evil.example/phishing',
            ]));

        $response
            ->assertRedirect(url('/cart'))
            ->assertSessionHas('locale', 'ar');
    }

    public function test_locale_switch_rejects_protocol_relative_redirect(): void
    {
        $response = $this
            ->from('/cart')
            ->get(route('locale.switch', [
                'locale' => 'ar',
                'redirect' => '//evil.example/phishing',
            ]));

        $response->assertRedirect(url('/cart'));
    }

    public function test_locale_switch_rejects_non_http_scheme_redirect(): void
    {
        $response = $this
            ->from('/cart')
            ->get(route('locale.switch', [
                'locale' => 'ar',
                'redirect' => 'javascript:alert(1)',
            ]));

        $response->assertRedirect(url('/cart'));
    }
}
