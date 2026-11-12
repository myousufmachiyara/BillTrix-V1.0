<?php

if (! function_exists('money')) {
    /** 12,345.50 — negative numbers in brackets for accounts style. */
    function money(float|int|string|null $value, int $decimals = 2, bool $brackets = false): string
    {
        $v = (float) $value;
        $s = number_format(abs($v), $decimals);

        return $v < 0 ? ($brackets ? "({$s})" : "-{$s}") : $s;
    }
}

if (! function_exists('qty')) {
    /** Quantity without trailing zeros: 12, 12.5, 0.25 */
    function qty(float|int|string|null $value): string
    {
        $s = number_format((float) $value, 4, '.', ',');

        return rtrim(rtrim($s, '0'), '.');
    }
}

if (! function_exists('drcr')) {
    /** Balance with Dr/Cr suffix. */
    function drcr(float|int|string|null $value): string
    {
        $v = round((float) $value, 2);

        return $v == 0 ? '0.00' : money(abs($v)).($v > 0 ? ' Dr' : ' Cr');
    }
}

if (! function_exists('in_words')) {
    /** 1250.5 → "One Thousand Two Hundred Fifty Rupees and Fifty Paisa Only" */
    function in_words(float|int|string|null $amount, string $currency = 'Rupees', string $fraction = 'Paisa'): string
    {
        $amount = round(abs((float) $amount), 2);
        $whole = (int) floor($amount);
        $cents = (int) round(($amount - $whole) * 100);

        $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve',
            'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
        $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

        $chunk = function (int $n) use ($ones, $tens): string {
            $out = [];
            if ($n >= 100) {
                $out[] = $ones[intdiv($n, 100)].' Hundred';
                $n %= 100;
            }
            if ($n >= 20) {
                $out[] = $tens[intdiv($n, 10)].($n % 10 ? ' '.$ones[$n % 10] : '');
            } elseif ($n > 0) {
                $out[] = $ones[$n];
            }

            return implode(' ', $out);
        };

        $words = [];
        foreach ([1_000_000_000 => 'Billion', 1_000_000 => 'Million', 1_000 => 'Thousand', 1 => ''] as $size => $name) {
            if ($whole >= $size) {
                $words[] = trim($chunk(intdiv($whole, $size)).' '.$name);
                $whole %= $size;
            }
        }

        $text = ($words ? implode(' ', $words) : 'Zero').' '.$currency;
        if ($cents) {
            $text .= ' and '.$chunk($cents).' '.$fraction;
        }

        return $text.' Only';
    }
}
