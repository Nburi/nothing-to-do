@php
    $today = auth()->user()->localToday();
    $isOverdue = $item['raw_date']->lessThan($today);
    $daysLate = $isOverdue ? (int) $item['raw_date']->diffInDays($today) : null;
@endphp
<div class="py-2.5 first:pt-0 last:pb-0">
    <p class="text-sm text-ink">{{ $item['title'] }}</p>
    <p class="mt-0.5 text-xs text-ink-soft">
        {{ $item['type'] === 'agenda' ? 'Hausaufgabe' : 'Aufgabe' }} · fällig {{ $item['raw_date']->isoFormat('D. MMM') }}
        @if ($daysLate !== null)
            · {{ $daysLate }} {{ $daysLate === 1 ? 'Tag' : 'Tage' }} überfällig
        @endif
    </p>
    <div class="mt-1.5 flex items-center gap-4">
        <a href="{{ route('schedule') }}" wire:navigate class="inline-flex items-center gap-1 text-xs font-medium text-overprint hover:underline">
            <svg class="h-3 w-3" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 3v3m2-3v3M4 6h8M5 5h6a1 1 0 0 1 1 1v6a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z"/></svg>
            Zeitplan öffnen
        </a>
        @if ($item['type'] === 'task')
            <a href="{{ route('app', ['task' => $item['id']]) }}" wire:navigate class="inline-flex items-center gap-1 text-xs font-medium text-overprint hover:underline">
                <svg class="h-3 w-3" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M10 3.5 12.5 6 6 12.5l-3 .5.5-3L10 3.5Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>
                Deadline ändern
            </a>
        @else
            <a href="{{ route('agenda') }}" wire:navigate class="inline-flex items-center gap-1 text-xs font-medium text-overprint hover:underline">
                <svg class="h-3 w-3" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M10 3.5 12.5 6 6 12.5l-3 .5.5-3L10 3.5Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>
                Deadline ändern
            </a>
        @endif
    </div>
</div>
