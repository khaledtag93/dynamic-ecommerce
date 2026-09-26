<?php

namespace Tests\Feature;

use App\Models\WebsiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SharedViewContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_storefront_partials_share_one_settings_read_per_request_and_refresh_on_the_next_request(): void
    {
        Route::get('/__tests/shared-view-context', static function () {
            $names = [];

            for ($index = 0; $index < 3; $index++) {
                $view = view('frontend.sections.personalized-product-strip', ['products' => collect()]);
                $view->render();
                $names[] = $view->getData()['storeSettings']['store_name_en'] ?? '';
            }

            return response(implode('|', $names));
        });

        WebsiteSetting::setValue('store_name_en', 'First shop', 'branding');
        $settingsReads = 0;
        DB::listen(static function ($query) use (&$settingsReads) {
            if (preg_match('/\bfrom\s+[`"]?website_settings[`"]?/i', $query->sql)) {
                $settingsReads++;
            }
        });

        $this->get('/__tests/shared-view-context')->assertOk()->assertSeeText('First shop|First shop|First shop');
        $this->assertSame(1, $settingsReads);

        WebsiteSetting::setValue('store_name_en', 'Second shop', 'branding');
        $settingsReads = 0;

        $this->get('/__tests/shared-view-context')->assertOk()->assertSeeText('Second shop|Second shop|Second shop');
        $this->assertSame(1, $settingsReads);
    }
}
