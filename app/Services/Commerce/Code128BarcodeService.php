<?php

namespace App\Services\Commerce;

use InvalidArgumentException;

class Code128BarcodeService
{
    /**
     * Code 128 module-width patterns indexed by symbol value.
     * Start B is 104 and stop is 106.
     */
    private const PATTERNS = [
        '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312',
        '132212', '221213', '221312', '231212', '112232', '122132', '122231', '113222',
        '123122', '123221', '223211', '221132', '221231', '213212', '223112', '312131',
        '311222', '321122', '321221', '312212', '322112', '322211', '212123', '212321',
        '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
        '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121',
        '313121', '211331', '231131', '213113', '213311', '213131', '311123', '311321',
        '331121', '312113', '312311', '332111', '314111', '221411', '431111', '111224',
        '111422', '121124', '121421', '141122', '141221', '112214', '112412', '122114',
        '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
        '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112',
        '421211', '212141', '214121', '412121', '111143', '111341', '131141', '114113',
        '114311', '411113', '411311', '113141', '114131', '311141', '411131', '211412',
        '211214', '211232', '2331112',
    ];

    /**
     * @return list<int>
     */
    public function encodedValues(string $value): array
    {
        $value = trim($value);

        if ($value === '' || ! preg_match('/^[\x20-\x7E]+$/', $value)) {
            throw new InvalidArgumentException('Code 128B supports non-empty printable ASCII identifiers only.');
        }

        $values = [104];

        foreach (str_split($value) as $character) {
            $values[] = ord($character) - 32;
        }

        $checksum = 104;

        foreach (array_slice($values, 1) as $position => $symbol) {
            $checksum += $symbol * ($position + 1);
        }

        $values[] = $checksum % 103;
        $values[] = 106;

        return $values;
    }

    public function toSvg(string $value, int $barHeight = 52, int $quietZone = 10): string
    {
        $symbols = $this->encodedValues($value);
        $totalModules = ($quietZone * 2);
        $patterns = [];

        foreach ($symbols as $symbol) {
            $pattern = self::PATTERNS[$symbol];
            $patterns[] = $pattern;
            $totalModules += array_sum(array_map('intval', str_split($pattern)));
        }

        $x = $quietZone;
        $rectangles = [];

        foreach ($patterns as $pattern) {
            foreach (str_split($pattern) as $index => $width) {
                $moduleWidth = (int) $width;

                if ($index % 2 === 0) {
                    $rectangles[] = sprintf(
                        '<rect x="%d" y="0" width="%d" height="%d" fill="#000"/>',
                        $x,
                        $moduleWidth,
                        $barHeight
                    );
                }

                $x += $moduleWidth;
            }
        }

        $escapedValue = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $svgHeight = $barHeight + 18;

        return sprintf(
            '<svg class="code128-barcode" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" role="img" aria-label="%s" preserveAspectRatio="xMidYMid meet" shape-rendering="crispEdges">%s<text x="%s" y="%d" text-anchor="middle" font-family="monospace" font-size="10" fill="#111">%s</text></svg>',
            $totalModules,
            $svgHeight,
            $escapedValue,
            implode('', $rectangles),
            $totalModules / 2,
            $barHeight + 13,
            $escapedValue
        );
    }
}
