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
            ->assertSee('Professional Commerce')
            ->assertSee('Advanced palette')
            ->assertSee('Reapply selected preset')
            ->assertSee('No unsaved changes');
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
        $this->assertFalse((bool) WebsiteSetting::getValue('show_home_featured_categories'));
        $this->assertFalse((bool) WebsiteSetting::getValue('show_home_manual_featured_products'));
        $this->assertFalse((bool) WebsiteSetting::getValue('show_home_trust_blocks'));
    }
}
