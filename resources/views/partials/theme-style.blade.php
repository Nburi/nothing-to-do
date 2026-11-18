{{-- The user's theme as CSS variables (App\Support\Theme, config/themes.php). Rendered in every
     page head before the stylesheet, so the first paint already has the right colours. --}}
@php($appTheme = \App\Support\Theme::current())
<meta name="theme-color" media="(prefers-color-scheme: light)" content="{{ \App\Support\Theme::chromeColor($appTheme['theme'], $appTheme['mode'] === 'dark' ? 'dark' : 'light') }}">
<meta name="theme-color" media="(prefers-color-scheme: dark)" content="{{ \App\Support\Theme::chromeColor($appTheme['theme'], $appTheme['mode'] === 'light' ? 'light' : 'dark') }}">
<style id="theme-vars">{!! \App\Support\Theme::css($appTheme['theme'], $appTheme['mode']) !!}</style>
