<?php

namespace App\Livewire\Support;

/**
 * The fixed, small "one color per person" palette for the Familie feature —
 * deliberately separate from this app's own 4-tone Topografie accent system
 * (forest/contour/overprint/signal), since every one of those already means
 * something specific elsewhere (today/done, important, deadline, destructive/
 * overdue) and a family member's card color must not collide with any of
 * that. See CLAUDE.md, "Familie — geteilte Aufgaben".
 *
 * Lives under App\Livewire\Support — NOT App\Support or App\Models — on
 * purpose: tailwind.config.js's `content` scan only globs
 * `./resources/views/**\/*.blade.php` and `./app/Livewire/**\/*.php` (plus
 * app/View), not app/Models or a generic app/Support. Every method below
 * returns a complete, literal Tailwind class token (never built by string
 * concatenation), so it has to live somewhere Tailwind's scanner actually
 * reads or the classes get silently purged from the production build — the
 * exact trap CLAUDE.md's "CSS classes applied only from JavaScript are
 * silently purged" lesson describes, just for PHP-built classes instead of
 * JS-applied ones.
 */
final class FamilyColors
{
    /** @var list<string> */
    public const KEYS = ['coral', 'amber', 'lime', 'teal', 'sky', 'indigo', 'violet', 'pink'];

    /** German label for a color swatch's own aria-label (accessibility, not shown as text otherwise). */
    public static function label(string $key): string
    {
        return match ($key) {
            'coral' => 'Koralle',
            'amber' => 'Bernstein',
            'lime' => 'Limette',
            'teal' => 'Petrol',
            'sky' => 'Himmelblau',
            'indigo' => 'Indigo',
            'violet' => 'Veilchen',
            'pink' => 'Pink',
            default => 'Farblos',
        };
    }

    /** Solid fill — the claimed-card background, the color-picker swatch. */
    public static function bg(string $key): string
    {
        return match ($key) {
            'coral' => 'bg-[rgb(var(--fam-coral))]',
            'amber' => 'bg-[rgb(var(--fam-amber))]',
            'lime' => 'bg-[rgb(var(--fam-lime))]',
            'teal' => 'bg-[rgb(var(--fam-teal))]',
            'sky' => 'bg-[rgb(var(--fam-sky))]',
            'indigo' => 'bg-[rgb(var(--fam-indigo))]',
            'violet' => 'bg-[rgb(var(--fam-violet))]',
            'pink' => 'bg-[rgb(var(--fam-pink))]',
            default => 'bg-ink-faint',
        };
    }

    /** Muted tint — assignee chips, member-list dots. */
    public static function softBg(string $key): string
    {
        return match ($key) {
            'coral' => 'bg-[rgb(var(--fam-coral)/0.16)]',
            'amber' => 'bg-[rgb(var(--fam-amber)/0.16)]',
            'lime' => 'bg-[rgb(var(--fam-lime)/0.16)]',
            'teal' => 'bg-[rgb(var(--fam-teal)/0.16)]',
            'sky' => 'bg-[rgb(var(--fam-sky)/0.16)]',
            'indigo' => 'bg-[rgb(var(--fam-indigo)/0.16)]',
            'violet' => 'bg-[rgb(var(--fam-violet)/0.16)]',
            'pink' => 'bg-[rgb(var(--fam-pink)/0.16)]',
            default => 'bg-line',
        };
    }

    /** Text tone matching a color — used on its own soft background. */
    public static function text(string $key): string
    {
        return match ($key) {
            'coral' => 'text-[rgb(var(--fam-coral))]',
            'amber' => 'text-[rgb(var(--fam-amber))]',
            'lime' => 'text-[rgb(var(--fam-lime))]',
            'teal' => 'text-[rgb(var(--fam-teal))]',
            'sky' => 'text-[rgb(var(--fam-sky))]',
            'indigo' => 'text-[rgb(var(--fam-indigo))]',
            'violet' => 'text-[rgb(var(--fam-violet))]',
            'pink' => 'text-[rgb(var(--fam-pink))]',
            default => 'text-ink-faint',
        };
    }

    /** Border tone matching a color — the picker's "currently selected" ring. */
    public static function border(string $key): string
    {
        return match ($key) {
            'coral' => 'border-[rgb(var(--fam-coral))]',
            'amber' => 'border-[rgb(var(--fam-amber))]',
            'lime' => 'border-[rgb(var(--fam-lime))]',
            'teal' => 'border-[rgb(var(--fam-teal))]',
            'sky' => 'border-[rgb(var(--fam-sky))]',
            'indigo' => 'border-[rgb(var(--fam-indigo))]',
            'violet' => 'border-[rgb(var(--fam-violet))]',
            'pink' => 'border-[rgb(var(--fam-pink))]',
            default => 'border-line',
        };
    }

    /**
     * Not a class — a literal CSS custom-property reference for an inline
     * `style="--fam-tap: …"` attribute (the claim-flood animation, see
     * resources/css/app.css — its keyframes wrap this in their own rgb()).
     * Safe to build with interpolation since it is never a class name
     * Tailwind needs to find; $key is always one of the fixed KEYS above,
     * never raw user input.
     */
    public static function cssVar(string $key): string
    {
        return in_array($key, self::KEYS, true) ? "var(--fam-{$key})" : 'var(--ink-faint)';
    }

    /**
     * A ready-to-use inline `background-color`/`color` value (already
     * rgb()-wrapped, unlike cssVar() above) — for the odd spot that needs a
     * real color property rather than a class, e.g. an assignee's initial
     * dot sitting on a plain surface instead of on their own claim-flood.
     */
    public static function rgb(string $key): string
    {
        return in_array($key, self::KEYS, true) ? "rgb(var(--fam-{$key}))" : 'rgb(var(--ink-faint))';
    }
}
