{{--
    One chip in the Zeitplan's all-day strip: a task deadline/Wunschtermin or an Agenda
    homework/exam entry, on its own date or (dashed) as an advance preview. Colours mirror the
    rest of the app: hard deadline = contour, soft Wunschtermin = neutral, Hausaufgabe = forest,
    Prüfung = overprint (see task-card.blade.php / agenda-entry.blade.php).
--}}
@php
    $styles = match (true) {
        $item['kind'] === 'agenda' && $item['subtype'] === 'exam' => ['dot' => 'border-overprint', 'tx' => 'text-overprint', 'bg' => 'bg-overprint-soft'],
        $item['kind'] === 'agenda' => ['dot' => 'border-forest', 'tx' => 'text-forest', 'bg' => 'bg-forest-soft'],
        $item['subtype'] === 'deadline' => ['dot' => 'border-contour', 'tx' => 'text-contour', 'bg' => 'bg-contour-soft'],
        default => ['dot' => 'border-ink-faint', 'tx' => 'text-ink-soft', 'bg' => 'bg-surface'],
    };
    $toggleAction = $item['kind'] === 'agenda' ? 'toggleDeadlineAgendaDone' : 'toggleDeadlineTaskDone';
    $sourceUrl = $item['kind'] === 'agenda' ? route('agenda') : url('/app');
    $sourceLabel = $item['kind'] === 'agenda' ? 'Zur Agenda' : 'Zum Board';
@endphp
<div
    @class([
        'group/dl flex items-center gap-1.5 rounded-[6px] px-1.5 py-1',
        $styles['bg'] => ! $item['isPreview'],
        'border border-dashed border-line' => $item['isPreview'],
    ])
    title="{{ $item['isPreview'] ? 'in '.$item['daysUntil'].' Tagen fällig · '.$item['title'] : $item['title'] }}"
>
    <button
        type="button"
        wire:click="{{ $toggleAction }}({{ $item['id'] }})"
        class="grid h-3 w-3 flex-none place-items-center rounded-full border-[1.5px] {{ $styles['dot'] }} transition hover:scale-110"
        aria-label="Erledigt markieren: {{ $item['title'] }}"
    ></button>

    <span class="truncate text-[11px] {{ $item['isPreview'] ? $styles['tx'].' opacity-80' : $styles['tx'] }}">{{ $item['title'] }}</span>

    @if ($item['isPreview'])
        <span class="flex-none text-[9.5px] {{ $styles['tx'] }} opacity-80" aria-hidden="true">in {{ $item['daysUntil'] }}T</span>
    @endif

    <a
        href="{{ $sourceUrl }}"
        wire:navigate
        class="ml-auto flex-none opacity-0 transition group-hover/dl:opacity-100 {{ $styles['tx'] }}"
        aria-label="{{ $sourceLabel }}: {{ $item['title'] }}"
        @click.stop
    >
        <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 17 17 7M9 7h8v8"/></svg>
    </a>
</div>
