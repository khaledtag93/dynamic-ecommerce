<?php

namespace Tests\Feature;

use Tests\TestCase;

class ShippingWorkspaceClosureTest extends TestCase
{
    public function test_shipping_navigation_exposes_current_page_semantics(): void
    {
        $nav = file_get_contents(resource_path('views/admin/settings/shipping/_nav.blade.php'));

        $this->assertSame(3, substr_count($nav, 'aria-current="page"'));
    }

    public function test_shipping_method_controls_have_explicit_labels(): void
    {
        $view = file_get_contents(resource_path('views/admin/settings/shipping/methods.blade.php'));

        foreach ([
            'shippingMethodName-{{ $method->id }}',
            'shippingMethodNameAr-{{ $method->id }}',
            'shippingMethodSort-{{ $method->id }}',
            'shippingMethodEtaMin-{{ $method->id }}',
            'shippingMethodEtaMax-{{ $method->id }}',
            'method-active-{{ $method->id }}',
            'shippingMethodNotes-{{ $method->id }}',
        ] as $controlId) {
            $this->assertStringContainsString('for="' . $controlId . '"', $view);
            $this->assertStringContainsString('id="' . $controlId . '"', $view);
        }
    }

    public function test_shipping_zone_and_rate_controls_have_explicit_labels(): void
    {
        $zones = file_get_contents(resource_path('views/admin/settings/shipping/zones.blade.php'));
        $rates = file_get_contents(resource_path('views/admin/settings/shipping/rates.blade.php'));

        foreach ([
            'newShippingZoneCode',
            'newShippingZoneName',
            'newShippingZoneNameAr',
            'newShippingZoneCountryCode',
            'newShippingZoneCountryName',
            'newShippingZonePriority',
            'newShippingZoneActive',
        ] as $controlId) {
            $this->assertStringContainsString('for="' . $controlId . '"', $zones);
            $this->assertStringContainsString('id="' . $controlId . '"', $zones);
        }

        foreach ([
            'shippingZoneCode-{{ $zone->id }}',
            'shippingZoneName-{{ $zone->id }}',
            'shippingZoneNameAr-{{ $zone->id }}',
            'shippingZoneCountryCode-{{ $zone->id }}',
            'shippingZoneCountryName-{{ $zone->id }}',
            'shippingZonePriority-{{ $zone->id }}',
            'shippingZoneActive-{{ $zone->id }}',
            'shippingZoneNotes-{{ $zone->id }}',
            'shippingZoneCity-{{ $zone->id }}',
        ] as $controlId) {
            $this->assertStringContainsString('for="' . $controlId . '"', $zones);
            $this->assertStringContainsString('id="' . $controlId . '"', $zones);
        }

        foreach ([
            'shippingRateActive-{{ $zone->id }}-{{ $method->id }}',
            'shippingRateAmount-{{ $zone->id }}-{{ $method->id }}',
            'shippingRateThreshold-{{ $zone->id }}-{{ $method->id }}',
            'shippingRateBasis-{{ $zone->id }}-{{ $method->id }}',
        ] as $controlId) {
            $this->assertStringContainsString('for="' . $controlId . '"', $rates);
            $this->assertStringContainsString('id="' . $controlId . '"', $rates);
        }
    }
}
