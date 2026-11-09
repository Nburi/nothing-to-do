<?php

namespace App\Support;

use App\Models\User;
use Throwable;

/**
 * The seven themes (config/themes.php) and the user's choice of theme and light/dark mode.
 *
 * Every page head renders css() for the signed-in user (partials/theme-style), so the whole
 * app, old screens included, wears the chosen theme without a second stylesheet per theme.
 */
final class Theme
{
    public const MODES = ['system', 'light', 'dark'];

    /** Fraction of the base colour in a soft tint; matches the 15% in app.css. */
    public const SOFT_WEIGHT = 0.15;

    /** @return list<string> */
    public static function ids(): array
    {
        return array_keys(config('themes.themes'));
    }

    public static function default(): string
    {
        return config('themes.default');
    }

    public static function exists(?string $id): bool
    {
        return $id !== null && array_key_exists($id, config('themes.themes'));
    }

    /** @return array<string, mixed> */
    public static function definition(string $id): array
    {
        return config('themes.themes.'.(self::exists($id) ? $id : self::default()));
    }

    public static function for(?User $user): string
    {
        $id = $user?->theme;

        return self::exists($id) ? $id : self::default();
    }

    public static function modeFor(?User $user): string
    {
        $mode = $user?->color_mode;

        return in_array($mode, self::MODES, true) ? $mode : 'system';
    }

    /**
     * Every --t-* variable of one theme in one mode, as name => value.
     *
     * @return array<string, string>
     */
    public static function variables(string $id, string $mode): array
    {
        $theme = self::definition($id);
        $mode = $mode === 'dark' ? 'dark' : 'light';
        $vars = [];

        foreach ($theme[$mode] as $token => $value) {
            $vars['--t-'.$token] = $value;
        }

        return $vars + [
            '--t-fd' => $theme['fonts']['display'],
            '--t-fb' => $theme['fonts']['body'],
            '--t-fm' => $theme['fonts']['mono'],
            '--t-dw' => (string) $theme['display_weight'],
            '--t-r' => $theme['radius'],
            '--t-rl' => $theme['radius_lg'],
            '--t-bw' => $theme['border'],
            '--t-sh' => $theme['shadow'][$mode],
        ];
    }

    /** One mode as an inline declaration list, for a data-theme-scope element (preview cards). */
    public static function inlineStyle(string $id, string $mode): string
    {
        return self::declarations(self::variables($id, $mode));
    }

    /**
     * The stylesheet for the page head: the theme on :root, with the dark values behind the
     * OS preference in "system" mode, or pinned in "light" / "dark".
     */
    public static function css(string $id, string $mode): string
    {
        $light = self::declarations(self::variables($id, 'light')).'color-scheme:light;';
        $dark = self::declarations(self::variables($id, 'dark')).'color-scheme:dark;';

        return match ($mode) {
            'light' => ":root{{$light}}",
            'dark' => ":root{{$dark}}",
            default => ":root{{$light}}@media (prefers-color-scheme: dark){:root{{$dark}}}",
        };
    }

    /** The browser chrome colour per mode (meta theme-color): the accent, as the old app had. */
    public static function chromeColor(string $id, string $mode): string
    {
        return self::definition($id)[$mode === 'dark' ? 'dark' : 'light']['accent'];
    }

    /**
     * Theme and mode for the current request. Never throws: an error page must still render
     * when the database is the thing that failed.
     *
     * @return array{theme: string, mode: string}
     */
    public static function current(): array
    {
        try {
            $user = auth()->user();
        } catch (Throwable) {
            $user = null;
        }

        return ['theme' => self::for($user), 'mode' => self::modeFor($user)];
    }

    /** @param  array<string, string>  $vars */
    private static function declarations(array $vars): string
    {
        $out = '';

        foreach ($vars as $name => $value) {
            // Values come from config, never from users; strip anything that could end the
            // declaration or the style element anyway.
            $out .= $name.':'.str_replace([';', '<', '}', '{'], '', $value).';';
        }

        return $out;
    }
}
