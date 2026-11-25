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

    /**
     * Step 5.2: the shell tints its panels with `color-mix(colour N %, base)` (the Werkbank columns, the
     * Stapel tray and page). Ink, soft and faint text on those must still reach 4.5:1; the first audit
     * found faint text at 4.2:1 on the Stapel page (Topografie light).
     */
    #[DataProvider('themesAndModes')]
    public function test_text_stays_readable_on_the_tinted_panels_of_the_shell(string $id, string $mode): void
    {
        $p = config("themes.themes.{$id}.{$mode}");
        // [tint token, percent, base token] exactly as written in the views.
        $panels = [
            'stapel page (warn 5 % on bg)' => ['warn', 5, 'bg'],
            'werkbank column (ink 4 % on bg)' => ['ink', 4, 'bg'],
            'stapel tray (ink 5 % on surface)' => ['ink', 5, 'surface'],
            'ink 3 % on surface' => ['ink', 3, 'surface'],
            'ink 4 % on surface' => ['ink', 4, 'surface'],
            'warn 6 % on surface' => ['warn', 6, 'surface'],
            'accent 12 % on surface' => ['accent', 12, 'surface'],
            // Selected chips and notices: a soft tint behind secondary text (the hint under a selected option).
            'accent-soft' => ['accent', 15, 'surface'],
            'good-soft' => ['good', 15, 'surface'],
            'warn-soft' => ['warn', 15, 'surface'],
            'bad-soft' => ['bad', 15, 'surface'],
        ];

        $failures = [];

        foreach ($panels as $name => [$tint, $percent, $base]) {
            $background = Contrast::mix($p[$tint], $p[$base], $percent / 100);

            foreach (['ink', 'soft', 'faint', 'accent-ink', 'good', 'warn', 'bad'] as $text) {
                // A tint is never behind text of its own colour family except in the pairs the first test covers.
                if (str_ends_with($name, '-soft') && in_array($text, ['accent-ink', 'good', 'warn', 'bad'], true)) {
                    continue;
                }
                $ratio = Contrast::ratio($p[$text], $background);

                if ($ratio < self::MIN) {
                    $failures[] = sprintf('%s on %s: %s on %s = %.2f', $text, $name, $p[$text], $background, $ratio);
                }
            }
        }

        $this->assertSame([], $failures, "{$id} {$mode} has text below 4.5:1 on a tinted panel");
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
