{{-- Stroke icons of the new shell. Usage: @include('shell.icon', ['name' => 'play', 'class' => 'size-4']) --}}
@php
    $paths = [
        'play' => '<path d="M8 5.5v13l10-6.5z"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'gear' => '<path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/>',
        'check' => '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
        'shuffle' => '<path d="M4 7h3.5c2 0 3 1 4.2 2.8l1.6 2.4C14.5 14 15.5 15 17.5 15H20M17 12l3 3-3 3M4 15h3.5c1 0 1.8-.3 2.4-.8M14.3 8.2c.7-.8 1.6-1.2 3.2-1.2H20M17 4l3 3-3 3"/>',
        'shrink' => '<path d="M9 4v5H4M15 20v-5h5M4 4l5 5M20 20l-5-5"/>',
        'pause' => '<path d="M8 5v14M16 5v14"/>',
        'stop' => '<rect x="6" y="6" width="12" height="12" rx="2"/>',
        'inbox' => '<path d="M3 13l3-8h12l3 8v6H3z"/><path d="M3 13h5l1 2h6l1-2h5"/>',
        'todos' => '<circle cx="6" cy="7" r="1.5"/><circle cx="6" cy="12" r="1.5"/><circle cx="6" cy="17" r="1.5"/><path d="M10 7h10M10 12h10M10 17h10"/>',
        'tasks' => '<rect x="4" y="4" width="16" height="16" rx="3"/><path d="M8 12l3 3 5-6"/>',
        'later' => '<path d="M3 8l9-5 9 5v8l-9 5-9-5z"/><path d="M3 8l9 5 9-5M12 13v8"/>',
        'desk' => '<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
        'close' => '<path d="M6 6l12 12M18 6L6 18"/>',
        'back' => '<path d="M15 18l-6-6 6-6"/>',
        'battery' => '<rect x="3" y="7" width="16" height="10" rx="2.5"/><path d="M21 10.5v3"/>',
        'dots' => '<circle cx="5" cy="12" r="1.2"/><circle cx="12" cy="12" r="1.2"/><circle cx="19" cy="12" r="1.2"/>',
        'reset' => '<path d="M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5z"/>',
        'alert' => '<circle cx="12" cy="12" r="9"/><path d="M12 7.5v5.5M12 16.5h.01"/>',
    ];
@endphp
<svg class="{{ $class ?? 'size-5' }} shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $paths[$name] !!}</svg>
