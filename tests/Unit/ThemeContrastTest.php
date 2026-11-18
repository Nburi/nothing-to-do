<?php

namespace Tests\Unit;

use App\Support\Contrast;
use App\Support\Theme;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * REQUIREMENTS § 7: contrast >= 4.5:1 for text in every theme, light and dark (step 2.1).
 *
 * Each pair is a text colour on a background the UI really puts it on. Soft tints are
 * computed with the same mix as app.css (color-mix in srgb, 15 % of the colour on surface).
 * Area colours are not here on purpose: they are only dots next to a label in ink.
 */
class ThemeContrastTest extends TestCase
{
    private const MIN = 4.5;

    /** @return iterable<string, array{string, string}> */
    public static function themesAndModes(): iterable
    {
        // Data providers run before the app boots, so read the file instead of config().
        $config = require __DIR__.'/../../config/themes.php';

        foreach (array_keys($config['themes']) as $id) {
            foreach (['light', 'dark'] as $mode) {
                yield "{$id} {$mode}" => [$id, $mode];
            }
        }
    }

    #[DataProvider('themesAndModes')]
    public function test_every_text_colour_reaches_4_5_to_1(string $id, string $mode): void
    {
        $p = config("themes.themes.{$id}.{$mode}");
        $soft = fn (string $token) => Contrast::mix($p[$token], $p['surface'], Theme::SOFT_WEIGHT);

        $pairs = [];

        foreach (['ink', 'soft', 'faint'] as $text) {
            $pairs["{$text} on bg"] = [$p[$text], $p['bg']];
            $pairs["{$text} on surface"] = [$p[$text], $p['surface']];
        }

        foreach (['accent', 'good', 'warn', 'bad'] as $tint) {
            $pairs["ink on {$tint}-soft"] = [$p['ink'], $soft($tint)];
        }

        foreach (['accent-ink' => 'accent', 'good' => 'good', 'warn' => 'warn', 'bad' => 'bad'] as $text => $tint) {
            $pairs["{$text} on surface"] = [$p[$text], $p['surface']];
            $pairs["{$text} on bg"] = [$p[$text], $p['bg']];
            $pairs["{$text} on {$tint}-soft"] = [$p[$text], $soft($tint)];
        }

        $pairs['accent-fg on accent'] = [$p['accent-fg'], $p['accent']];
        $pairs['on-deep on deep'] = [$p['on-deep'], $p['deep']];
        $pairs['bg on ink (selected chip)'] = [$p['bg'], $p['ink']];

        $failures = [];

        foreach ($pairs as $name => [$fg, $bg]) {
            $ratio = Contrast::ratio($fg, $bg);

            if ($ratio < self::MIN) {
                $failures[] = sprintf('%s: %s on %s = %.2f', $name, $fg, $bg, $ratio);
            }
        }

        $this->assertSame([], $failures, "{$id} {$mode} has text below 4.5:1");
    }

    public function test_every_theme_defines_the_same_tokens(): void
    {
        $expected = array_keys(config('themes.themes.topografie.light'));

        foreach (Theme::ids() as $id) {
            foreach (['light', 'dark'] as $mode) {
                $this->assertSame($expected, array_keys(config("themes.themes.{$id}.{$mode}")), "{$id} {$mode}");
            }
        }
    }

    public function test_the_seven_planned_themes_exist_and_topografie_is_the_default(): void
    {
        $this->assertSame(['topografie', 'nachtzug', 'gletscher', 'kiosk', 'moos', 'abendrot', 'tinte'], Theme::ids());
        $this->assertSame('topografie', Theme::default());
    }

    public function test_the_ratio_matches_the_wcag_reference_values(): void
    {
        $this->assertEqualsWithDelta(21.0, Contrast::ratio('#000000', '#FFFFFF'), 0.01);
        $this->assertEqualsWithDelta(4.54, Contrast::ratio('#767676', '#FFFFFF'), 0.01);
        $this->assertSame('#808080', Contrast::mix('#FFFFFF', '#000000', 0.5025));
    }
}
