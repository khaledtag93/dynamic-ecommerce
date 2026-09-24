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
        $admin = User::factory()->create(['role_as' => 1]);

        $response = $this->actingAs($admin)
            ->get(route('admin.settings.content'));

        $response
            ->assertOk()
            ->assertSee('Store Content & Policies')
            ->assertSee('Publish only policy text reviewed for your business and jurisdiction.');

        $html = $response->getContent();

        $this->assertSame(7, substr_count($html, 'class="form-check form-switch m-0 flex-nowrap"'));
        $this->assertStringContainsString('id="contact_show_email"', $html);
        $this->assertStringContainsString('id="contact_show_hours"', $html);
        $this->assertStringContainsString('id="orders_allow_customer_cancellation"', $html);
        $this->assertStringNotContainsString('form-check admin-switch-card h-100 d-block', $html);
    }
}
