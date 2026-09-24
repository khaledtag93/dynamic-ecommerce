<?php

namespace Tests\Unit;

use App\Services\Commerce\Code128BarcodeService;
use InvalidArgumentException;
use Tests\TestCase;

class Code128BarcodeServiceTest extends TestCase
{
    public function test_code_128b_encodes_known_values_and_checksum(): void
    {
        $service = new Code128BarcodeService();

        $this->assertSame([104, 33, 34, 102, 106], $service->encodedValues('AB'));
    }

    public function test_code_128b_svg_contains_real_bar_geometry_and_safe_text(): void
    {
        $service = new Code128BarcodeService();

        $svg = $service->toSvg('ABC-123');

        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('shape-rendering="crispEdges"', $svg);
        $this->assertStringContainsString('<rect ', $svg);
        $this->assertStringContainsString('ABC-123', $svg);
        $this->assertStringNotContainsString('<script', $svg);
    }

    public function test_code_128b_rejects_blank_or_non_ascii_identifiers(): void
    {
        $service = new Code128BarcodeService();

        try {
            $service->encodedValues('');
            $this->fail('Blank identifiers should not be encoded.');
        } catch (InvalidArgumentException) {
            $this->assertTrue(true);
        }

        $this->expectException(InvalidArgumentException::class);
        $service->encodedValues('باركود');
    }
}
