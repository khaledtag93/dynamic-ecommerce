<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WebsiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandingSettingsExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_branding_workspace_exposes_visual_professional_theme_controls(): void
    {
        $owner = $this->createSuperAdmin();

        $this->actingAs($owner)
            ->get(route('admin.settings.branding'))
            ->assertOk()
            ->assertViewHas('presets', fn (array $presets) => isset($presets['professional_commerce'])
                && ($presets['professional_commerce']['brand_primary_color'] ?? null) === '#2563eb'
                && ($presets['professional_commerce']['brand_secondary_color'] ?? null) === '#0f172a')
            ->assertSee('data-theme-preset-choice="professional_commerce"', false)
            ->assertSee('branding-advanced-palette', false)
            ->assertSee('brandingSaveState', false);
    }

    public function test_extended_professional_theme_catalog_is_available(): void
    {
        $owner = $this->createSuperAdmin();

        $this->actingAs($owner)
            ->get(route('admin.settings.branding'))
            ->assertOk()
            ->assertViewHas('presets', fn (array $presets) =>
                ($presets['royal_navy']['brand_primary_color'] ?? null) === '#1d4ed8'
                && ($presets['emerald_studio']['brand_primary_color'] ?? null) === '#047857'
                && ($presets['plum_editorial']['brand_primary_color'] ?? null) === '#7e22ce'
                && ($presets['royal_navy']['customer_badge_style'] ?? null) === 'outline'
                && ($presets['emerald_studio']['customer_badge_style'] ?? null) === 'soft'
                && ($presets['plum_editorial']['customer_badge_style'] ?? null) === 'pill'
            )
            ->assertSee('data-theme-preset-choice="royal_navy"', false)
            ->assertSee('data-theme-preset-choice="emerald_studio"', false)
            ->assertSee('data-theme-preset-choice="plum_editorial"', false);
    }

    public function test_extended_theme_can_be_saved_with_its_complete_visual_contract(): void
    {
        $owner = $this->createSuperAdmin();

        $this->actingAs($owner)
            ->put(route('admin.settings.branding.update'), [
                'project_name' => 'Tag Marketplace',
                'store_name' => 'Tag Market Place',
                'theme_preset' => 'royal_navy',
                'default_locale' => 'en',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('royal_navy', WebsiteSetting::getValue('theme_preset'));
        $this->assertSame('#1d4ed8', WebsiteSetting::getValue('brand_primary_color'));
        $this->assertSame('#172554', WebsiteSetting::getValue('brand_secondary_color'));
        $this->assertSame('#f59e0b', WebsiteSetting::getValue('brand_accent_color'));
        $this->assertSame('16', (string) WebsiteSetting::getValue('customer_card_radius'));
        $this->assertSame('outline', WebsiteSetting::getValue('customer_badge_style'));
    }

    public function test_custom_theme_keeps_its_own_identity_and_current_visual_values(): void
    {
        $owner = $this->createSuperAdmin();

        $this->actingAs($owner)
            ->put(route('admin.settings.branding.update'), [
                'project_name' => 'Tag Marketplace',
                'store_name' => 'Tag Market Place',
                'theme_preset' => 'royal_navy',
                'brand_primary_color' => '#123456',
                'brand_secondary_color' => '#234567',
                'brand_accent_color' => '#345678',
                'customer_card_radius' => 22,
                'customer_badge_style' => 'pill',
                'custom_theme_name' => 'My Retail Theme',
                'save_as_custom_theme' => 1,
                'default_locale' => 'en',
            ])
            ->assertSessionHasNoErrors();

        $themes = json_decode((string) WebsiteSetting::getValue('custom_themes', '[]'), true);

        $this->assertSame('custom_my_retail_theme', WebsiteSetting::getValue('theme_preset'));
        $this->assertSame('custom_my_retail_theme', $themes['custom_my_retail_theme']['theme_preset'] ?? null);
        $this->assertSame('My Retail Theme', $themes['custom_my_retail_theme']['theme_label'] ?? null);
        $this->assertSame('#123456', $themes['custom_my_retail_theme']['brand_primary_color'] ?? null);
        $this->assertSame('22', (string) ($themes['custom_my_retail_theme']['customer_card_radius'] ?? null));
        $this->assertSame('pill', $themes['custom_my_retail_theme']['customer_badge_style'] ?? null);
    }

    public function test_professional_theme_can_be_saved_and_homepage_toggles_normalize(): void
    {
        $owner = $this->createSuperAdmin();
        WebsiteSetting::setValue('show_home_featured_categories', true, 'branding');
        WebsiteSetting::setValue('show_home_manual_featured_products', true, 'branding');
        WebsiteSetting::setValue('show_home_trust_blocks', true, 'branding');

        $this->actingAs($owner)
            ->put(route('admin.settings.branding.update'), [
                'project_name' => 'Tag Marketplace',
                'store_name' => 'Tag Market Place',
                'theme_preset' => 'professional_commerce',
                'default_locale' => 'ar',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('professional_commerce', WebsiteSetting::getValue('theme_preset'));
        $this->assertSame('#2563eb', WebsiteSetting::getValue('brand_primary_color'));
        $this->assertSame('#0f172a', WebsiteSetting::getValue('brand_secondary_color'));
        $this->assertSame('#0891b2', WebsiteSetting::getValue('brand_accent_color'));
        $this->assertFalse(filter_var(WebsiteSetting::getValue('show_home_featured_categories'), FILTER_VALIDATE_BOOLEAN));
        $this->assertFalse(filter_var(WebsiteSetting::getValue('show_home_manual_featured_products'), FILTER_VALIDATE_BOOLEAN));
        $this->assertFalse(filter_var(WebsiteSetting::getValue('show_home_trust_blocks'), FILTER_VALIDATE_BOOLEAN));
    }
    public function test_trust_blocks_can_be_explicitly_disabled_by_omitting_checkbox_fields(): void
    {
        $owner = $this->createSuperAdmin();
        foreach (range(1, 4) as $index) {
            WebsiteSetting::setValue("trust_block_{$index}_active", true, 'branding');
        }

        $this->actingAs($owner)
            ->put(route('admin.settings.branding.update'), [
                'project_name' => 'Tag Marketplace',
                'store_name' => 'Tag Market Place',
                'theme_preset' => 'professional_commerce',
                'default_locale' => 'en',
            ])
            ->assertSessionHasNoErrors();

        foreach (range(1, 4) as $index) {
            $this->assertFalse(filter_var(WebsiteSetting::getValue("trust_block_{$index}_active"), FILTER_VALIDATE_BOOLEAN));
        }
    }

    public function test_unknown_theme_preset_is_rejected(): void
    {
        $owner = $this->createSuperAdmin();

        $this->actingAs($owner)
            ->put(route('admin.settings.branding.update'), [
                'project_name' => 'Tag Marketplace',
                'store_name' => 'Tag Market Place',
                'theme_preset' => 'not_a_real_theme',
                'default_locale' => 'en',
            ])
            ->assertSessionHasErrors('theme_preset');

        $this->assertNotSame('not_a_real_theme', WebsiteSetting::getValue('theme_preset'));
    }

    public function test_homepage_section_order_is_normalized_before_persistence(): void
    {
        $owner = $this->createSuperAdmin();

        $this->actingAs($owner)
            ->put(route('admin.settings.branding.update'), [
                'project_name' => 'Tag Marketplace',
                'store_name' => 'Tag Market Place',
                'theme_preset' => 'professional_commerce',
                'default_locale' => 'en',
                'homepage_sections_order' => 'trust_blocks,hero,trust_blocks,unknown_section',
            ])
            ->assertSessionHasNoErrors();

        $order = explode(',', (string) WebsiteSetting::getValue('homepage_sections_order'));

        $this->assertSame('trust_blocks', $order[0] ?? null);
        $this->assertSame('hero', $order[1] ?? null);
        $this->assertSame(1, count(array_keys($order, 'trust_blocks', true)));
        $this->assertNotContains('unknown_section', $order);
        $this->assertContains('featured_products', $order);
        $this->assertContains('categories', $order);
    }

    public function test_unsupported_default_locale_is_rejected(): void
    {
        $owner = $this->createSuperAdmin();

        $this->actingAs($owner)
            ->put(route('admin.settings.branding.update'), [
                'project_name' => 'Tag Marketplace',
                'store_name' => 'Tag Market Place',
                'theme_preset' => 'professional_commerce',
                'default_locale' => 'fr',
            ])
            ->assertSessionHasErrors('default_locale');

        $this->assertNotSame('fr', WebsiteSetting::getValue('default_locale'));
    }

    public function test_unsafe_branding_storefront_links_are_rejected(): void
    {
        $owner = $this->createSuperAdmin();

        $this->actingAs($owner)
            ->put(route('admin.settings.branding.update'), [
                'project_name' => 'Tag Marketplace',
                'store_name' => 'Tag Market Place',
                'theme_preset' => 'professional_commerce',
                'default_locale' => 'en',
                'hero_primary_button_link' => 'javascript:alert(1)',
                'promo_banner_1_button_link' => 'data:text/html,<script>alert(1)</script>',
            ])
            ->assertSessionHasErrors(['hero_primary_button_link', 'promo_banner_1_button_link']);
    }

    public function test_safe_branding_relative_anchor_and_web_links_are_accepted(): void
    {
        $owner = $this->createSuperAdmin();

        $this->actingAs($owner)
            ->put(route('admin.settings.branding.update'), [
                'project_name' => 'Tag Marketplace',
                'store_name' => 'Tag Market Place',
                'theme_preset' => 'professional_commerce',
                'default_locale' => 'en',
                'hero_primary_button_link' => '#featured-products',
                'hero_secondary_button_link' => '/categories',
                'home_manual_featured_products_action_link' => '?sort=latest',
                'home_promo_button_link' => 'https://example.com/offers',
                'home_promo_secondary_button_link' => 'mailto:sales@example.com',
                'promo_banner_1_button_link' => 'tel:+201234567890',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('#featured-products', WebsiteSetting::getValue('hero_primary_button_link'));
        $this->assertSame('/categories', WebsiteSetting::getValue('hero_secondary_button_link'));
        $this->assertSame('https://example.com/offers', WebsiteSetting::getValue('home_promo_button_link'));
    }

}
