<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * WCAG 2 contrast maths for the theme tokens (REQUIREMENTS § 7: text >= 4.5:1 in every theme).
 *
 * mix() is the same blend the stylesheet uses for the soft tints,
 * `color-mix(in srgb, A p%, B)`: a straight per-channel mix of the gamma-encoded sRGB values.
 */
final class Contrast
{
    public static function ratio(string $a, string $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    /** $weight of $a, the rest of $b. */
    public static function mix(string $a, string $b, float $weight): string
    {
        $ca = self::channels($a);
        $cb = self::channels($b);
        $out = '#';

        for ($i = 0; $i < 3; $i++) {
            $out .= sprintf('%02X', (int) round($ca[$i] * $weight + $cb[$i] * (1 - $weight)));
        }

        return $out;
    }

    public static function luminance(string $hex): float
    {
        $weights = [0.2126, 0.7152, 0.0722];
        $sum = 0.0;

        foreach (self::channels($hex) as $i => $channel) {
            $c = $channel / 255;
            $c = $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
            $sum += $weights[$i] * $c;
        }

        return $sum;
    }

    /** @return array{0:int,1:int,2:int} */
    private static function channels(string $hex): array
    {
        if (! preg_match('/^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i', $hex, $m)) {
            throw new InvalidArgumentException("Not a #RRGGBB colour: {$hex}");
        }

        return [hexdec($m[1]), hexdec($m[2]), hexdec($m[3])];
    }
}
