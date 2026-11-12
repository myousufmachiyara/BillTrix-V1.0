<?php

namespace App\Support;

/**
 * Code 128 barcode as inline SVG (no images or packages needed). Uses code set C
 * for runs of digits (compact numeric barcodes like EAN-13 numbers) and code set B
 * for everything else. Every hand scanner reads Code 128.
 */
class Code128
{
    private const PATTERNS = [
        '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
        '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
        '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
        '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
        '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
        '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
        '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
        '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
        '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
        '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
        '114131', '311141', '411131', '211412', '211214', '211232', '2331112',
    ];

    private const START_B = 104;

    private const START_C = 105;

    private const CODE_B = 100;

    private const CODE_C = 99;

    private const STOP = 106;

    /** @return array<int> symbol values including start, checksum and stop */
    public static function encode(string $text): array
    {
        $text = preg_replace('/[^\x20-\x7E]/', '', $text);
        $codes = [];
        $set = null;
        $i = 0;
        $len = strlen($text);
        while ($i < $len) {
            $digits = strspn($text, '0123456789', $i);
            // Switch to set C for 4+ digits (or 2+ at the very start/end of an all-digit tail).
            $useC = $digits >= 4 || ($digits >= 2 && $digits === $len - $i && $set !== 'B' && $digits % 2 === 0);
            if ($useC) {
                $pairs = intdiv($digits, 2);
                if ($set !== 'C') {
                    $codes[] = $set === null ? self::START_C : self::CODE_C;
                    $set = 'C';
                }
                for ($p = 0; $p < $pairs; $p++, $i += 2) {
                    $codes[] = (int) substr($text, $i, 2);
                }

                continue;
            }
            if ($set !== 'B') {
                $codes[] = $set === null ? self::START_B : self::CODE_B;
                $set = 'B';
            }
            $codes[] = ord($text[$i]) - 32;
            $i++;
        }
        if (! $codes) {
            $codes[] = self::START_B;
        }

        $sum = $codes[0];
        foreach (array_slice($codes, 1) as $pos => $c) {
            $sum += ($pos + 1) * $c;
        }
        $codes[] = $sum % 103;
        $codes[] = self::STOP;

        return $codes;
    }

    /** Inline SVG; width in modules, scaled to fill its box. */
    public static function svg(string $text, int $height = 40): string
    {
        $x = 10; // quiet zone
        $bars = '';
        foreach (self::encode($text) as $code) {
            foreach (str_split(self::PATTERNS[$code]) as $k => $w) {
                if ($k % 2 === 0) {
                    $bars .= '<rect x="'.$x.'" y="0" width="'.$w.'" height="'.$height.'"/>';
                }
                $x += (int) $w;
            }
        }
        $width = $x + 10;

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$width.' '.$height.'" preserveAspectRatio="none" shape-rendering="crispEdges" role="img" aria-label="'.e($text).'"><g fill="#000">'.$bars.'</g></svg>';
    }
}
