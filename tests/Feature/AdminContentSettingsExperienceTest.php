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

        $this->assertSame(13, substr_count($html, 'class="form-check form-switch m-0 flex-nowrap"'));
        $this->assertStringContainsString('id="contact_show_email"', $html);
        $this->assertStringContainsString('id="contact_show_hours"', $html);
        $this->assertStringContainsString('id="orders_allow_customer_cancellation"', $html);
        $this->assertStringContainsString('id="footer_show_shop"', $html);
        $this->assertStringContainsString('id="footer_show_support"', $html);
        $this->assertStringContainsString('id="footer_show_trust"', $html);
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
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('website_settings', ['key' => 'footer_show_shop', 'value' => '1']);
        $this->assertDatabaseHas('website_settings', ['key' => 'footer_show_policies', 'value' => '0']);
        $this->assertDatabaseHas('website_settings', ['key' => 'footer_show_support', 'value' => '1']);
        $this->assertDatabaseHas('website_settings', ['key' => 'footer_show_experience_note', 'value' => '0']);
    }

}
