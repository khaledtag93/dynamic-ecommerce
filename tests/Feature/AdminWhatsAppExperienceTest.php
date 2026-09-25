<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminWhatsAppExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_whatsapp_settings_use_sectioned_workspace_and_aligned_switch_cards(): void
    {
        $owner = $this->createSuperAdmin();

        $response = $this->actingAs($owner)
            ->get(route('admin.settings.whatsapp'));

        $response
            ->assertOk()
            ->assertSee('Channel & queue')
            ->assertSee('Provider & templates')
            ->assertSee('Test tools')
            ->assertSee('WhatsApp logs');

        $html = $response->getContent();

        foreach (['wa-overview', 'wa-configuration', 'wa-provider', 'wa-templates', 'wa-test-tools', 'wa-logs'] as $id) {
            $this->assertSame(1, substr_count($html, 'id="'.$id.'"'));
        }

        $this->assertSame(5, substr_count($html, 'class="wa-switch-card'));
        $this->assertSame(5, substr_count($html, 'form-check form-switch admin-switch-wrap m-0'));
        $this->assertSame(5, substr_count($html, 'admin-card admin-stat-card wa-metric-card'));
    }
}
