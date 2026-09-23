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
        $owner = User::factory()->create(['role_as' => 1]);

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

    public function test_professional_theme_can_be_saved_and_homepage_toggles_normalize(): void
    {
        $owner = User::factory()->create(['role_as' => 1]);
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
}
