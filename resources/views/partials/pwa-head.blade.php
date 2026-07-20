{{-- PWA metadata (manifest + icons). Shared by layouts/app, layouts/guest, and welcome —
     the one thing all three otherwise-independent <head> blocks have identically in common. --}}
<link rel="manifest" href="{{ asset('manifest.json') }}">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('icons/icon-32.png') }}">
<link rel="icon" type="image/png" sizes="16x16" href="{{ asset('icons/icon-16.png') }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('icons/icon-180.png') }}">
