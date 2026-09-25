<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminContentSettingsExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_content_switches_use_aligned_toggle_layout(): void
    {
        $admin = $this->createSuperAdmin();

        $response = $this->actingAs($admin)
            ->get(route('admin.settings.content'));

        $response
            ->assertOk()
            ->assertSee('Store Content & Policies')
            ->assertSee('Publish only policy text reviewed for your business and jurisdiction.');

        $html = $response->getContent();

        $this->assertSame(16, substr_count($html, 'class="form-check form-switch m-0 flex-nowrap"'));
        $this->assertStringContainsString('id="contact_show_email"', $html);
        $this->assertStringContainsString('id="contact_show_hours"', $html);
        $this->assertStringContainsString('id="orders_allow_customer_cancellation"', $html);
        $this->assertStringContainsString('id="footer_show_shop"', $html);
        $this->assertStringContainsString('id="footer_show_support"', $html);
        $this->assertStringContainsString('id="footer_show_trust"', $html);
        $this->assertStringContainsString('id="footer_show_whatsapp"', $html);
        $this->assertStringContainsString('id="footer_show_website"', $html);
        $this->assertStringContainsString('name="footer_trust_1_text_en"', $html);
        $this->assertStringContainsString('name="footer_trust_1_text_ar"', $html);
        $this->assertStringContainsString('name="footer_trust_3_text_ar"', $html);
        $this->assertStringContainsString('id="footer_show_social"', $html);
        $this->assertStringContainsString('name="store_social_facebook"', $html);
        $this->assertStringContainsString('name="store_social_linkedin"', $html);
        $this->assertStringNotContainsString('form-check admin-switch-card h-100 d-block', $html);
    }
    public function test_footer_visibility_settings_are_persisted(): void
    {
        $admin = $this->createSuperAdmin();

        $this->actingAs($admin)
            ->put(route('admin.settings.content.update'), [
                'footer_show_shop' => '1',
                'footer_show_support' => '1',
                'footer_show_trust' => '1',
                'footer_show_whatsapp' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('website_settings', ['key' => 'footer_show_shop', 'value' => '1']);
        $this->assertDatabaseHas('website_settings', ['key' => 'footer_show_policies', 'value' => '0']);
        $this->assertDatabaseHas('website_settings', ['key' => 'footer_show_support', 'value' => '1']);
        $this->assertDatabaseHas('website_settings', ['key' => 'footer_show_experience_note', 'value' => '0']);
        $this->assertDatabaseHas('website_settings', ['key' => 'footer_show_whatsapp', 'value' => '1']);
        $this->assertDatabaseHas('website_settings', ['key' => 'footer_show_website', 'value' => '0']);
    }

    public function test_footer_trust_copy_is_persisted_bilingually(): void
    {
        $admin = $this->createSuperAdmin();

        $this->actingAs($admin)
            ->put(route('admin.settings.content.update'), [
                'footer_show_trust' => '1',
                'footer_trust_1_text_en' => 'Protected payments',
                'footer_trust_1_text_ar' => 'مدفوعات محمية',
                'footer_trust_2_text_en' => 'Helpful service',
                'footer_trust_2_text_ar' => 'خدمة مفيدة',
                'footer_trust_3_text_en' => 'Clear information',
                'footer_trust_3_text_ar' => 'معلومات واضحة',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('website_settings', ['key' => 'footer_trust_1_text_en', 'value' => 'Protected payments']);
        $this->assertDatabaseHas('website_settings', ['key' => 'footer_trust_1_text_ar', 'value' => 'مدفوعات محمية']);
        $this->assertDatabaseHas('website_settings', ['key' => 'footer_trust_3_text_en', 'value' => 'Clear information']);
    }


    public function test_footer_social_channels_are_persisted_and_can_be_hidden(): void
    {
        $admin = $this->createSuperAdmin();

        $this->actingAs($admin)
            ->put(route('admin.settings.content.update'), [
                'footer_show_social' => '1',
                'store_social_facebook' => 'https://www.facebook.com/tagmarketplace',
                'store_social_instagram' => 'https://www.instagram.com/tagmarketplace',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('website_settings', ['key' => 'footer_show_social', 'value' => '1']);
        $this->assertDatabaseHas('website_settings', ['key' => 'store_social_facebook', 'value' => 'https://www.facebook.com/tagmarketplace']);
        $this->assertDatabaseHas('website_settings', ['key' => 'store_social_instagram', 'value' => 'https://www.instagram.com/tagmarketplace']);

        $this->actingAs($admin)
            ->put(route('admin.settings.content.update'), [
                'store_social_facebook' => 'https://www.facebook.com/tagmarketplace',
                'store_social_instagram' => 'https://www.instagram.com/tagmarketplace',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('website_settings', ['key' => 'footer_show_social', 'value' => '0']);
    }


    public function test_footer_social_channels_reject_non_web_urls(): void
    {
        $admin = $this->createSuperAdmin();

        $this->actingAs($admin)
            ->from(route('admin.settings.content'))
            ->put(route('admin.settings.content.update'), [
                'footer_show_social' => '1',
                'store_social_facebook' => 'ftp://example.com/profile',
            ])
            ->assertRedirect(route('admin.settings.content'))
            ->assertSessionHasErrors('store_social_facebook');
    }

}
