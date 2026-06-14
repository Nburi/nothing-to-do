import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    // Respect the OS light/dark preference. The "map at night" palette lives in
    // app.css behind a prefers-color-scheme media query, exposed via CSS vars.
    darkMode: 'media',
    content: [
        './resources/views/**/*.blade.php',
        './app/Livewire/**/*.php',
        './app/View/**/*.php',
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
    ],
    theme: {
        extend: {
            fontFamily: {
                // Space Grotesk — the precise, cartographic grotesque of "Topografie".
                sans: ['Space Grotesk', 'ui-sans-serif', 'system-ui', 'sans-serif'],
            },
            colors: {
                // Semantic Topografie tokens. Channels live in CSS vars (space-
                // separated RGB) so a single media query flips the whole map between
                // day and night, AND Tailwind opacity modifiers (bg-paper/85) work.
                paper: 'rgb(var(--paper) / <alpha-value>)',     // page background
                surface: 'rgb(var(--surface) / <alpha-value>)', // raised cards & rows
                line: 'rgb(var(--line) / <alpha-value>)',       // hairlines (map grid)
                ink: {
                    DEFAULT: 'rgb(var(--ink) / <alpha-value>)',     // primary text (moss-ink)
                    soft: 'rgb(var(--ink-soft) / <alpha-value>)',   // secondary text
                    faint: 'rgb(var(--ink-faint) / <alpha-value>)', // hints / placeholders
                },
                forest: {
                    DEFAULT: 'rgb(var(--forest) / <alpha-value>)', // "go" — today / done
                    soft: 'rgb(var(--forest-soft) / <alpha-value>)',
                },
                overprint: {
                    DEFAULT: 'rgb(var(--overprint) / <alpha-value>)', // accent — important
                    soft: 'rgb(var(--overprint-soft) / <alpha-value>)',
                },
                contour: {
                    DEFAULT: 'rgb(var(--contour) / <alpha-value>)', // map brown — deadlines
                    soft: 'rgb(var(--contour-soft) / <alpha-value>)',
                },
                signal: {
                    DEFAULT: 'rgb(var(--signal) / <alpha-value>)', // red — destructive / overdue
                    soft: 'rgb(var(--signal-soft) / <alpha-value>)',
                },
            },
            borderRadius: {
                // One radius scale, used consistently (shape-consistency lock).
                card: '0.625rem',
            },
            transitionTimingFunction: {
                // A calm, slightly-overshooting curve for tactile feedback.
                tactile: 'cubic-bezier(0.16, 1, 0.3, 1)',
            },
            keyframes: {
                'punch-in': {
                    '0%': { transform: 'scale(0.6)', opacity: '0' },
                    '60%': { transform: 'scale(1.15)', opacity: '1' },
                    '100%': { transform: 'scale(1)', opacity: '1' },
                },
                'rise': {
                    '0%': { transform: 'translateY(4px)', opacity: '0' },
                    '100%': { transform: 'translateY(0)', opacity: '1' },
                },
            },
            animation: {
                'punch-in': 'punch-in 0.32s cubic-bezier(0.16, 1, 0.3, 1)',
                'rise': 'rise 0.28s cubic-bezier(0.16, 1, 0.3, 1) both',
            },
        },
    },
    plugins: [forms],
};
