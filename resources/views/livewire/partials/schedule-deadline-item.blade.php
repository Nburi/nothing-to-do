{{--
    One chip in the Zeitplan's all-day strip: a task deadline/Wunschtermin, an Agenda
    homework/exam entry, or a Planer placement, on its own date or (dashed) as an advance preview.
    Colours mirror the rest of the app: hard deadline = contour, soft Wunschtermin = neutral,
    Hausaufgabe = forest, Prüfung = overprint (see task-card.blade.php / agenda-entry.blade.php).
    A Planer placement (subtype=planned) gets a filled dot instead of a hollow ring — the one
    visual cue that distinguishes "you scheduled work on this" from an actual deadline, since every
    accent tone is already spoken for by the other three kinds.
--}}
@php
    $styles = match (true) {
        $item['kind'] === 'agenda' && $item['subtype'] === 'exam' => ['dot' => 'border-overprint', 'tx' => 'text-overprint', 'bg' => 'bg-overprint-soft'],
        $item['kind'] === 'agenda' => ['dot' => 'border-forest', 'tx' => 'text-forest', 'bg' => 'bg-forest-soft'],
        $item['subtype'] === 'deadline' => ['dot' => 'border-contour', 'tx' => 'text-contour', 'bg' => 'bg-contour-soft'],
        $item['subtype'] === 'planned' => ['dot' => 'border-ink-soft bg-ink-soft', 'tx' => 'text-ink-soft', 'bg' => 'bg-line/50'],
        default => ['dot' => 'border-ink-faint', 'tx' => 'text-ink-soft', 'bg' => 'bg-surface'],
    };
    $toggleAction = $item['kind'] === 'agenda' ? 'toggleDeadlineAgendaDone' : 'toggleDeadlineTaskDone';
    $sourceUrl = match (true) {
        $item['kind'] === 'agenda' => route('agenda'),
        $item['subtype'] === 'planned' => route('planner'),
        default => url('/app'),
    };
    $sourceLabel = match (true) {
        $item['kind'] === 'agenda' => 'Zur Agenda',
        $item['subtype'] === 'planned' => 'Zum Planer',
        default => 'Zum Board',
    };
    $tooltip = match (true) {
        $item['isPreview'] => 'in '.$item['daysUntil'].' Tagen fällig · '.$item['title'],
        $item['subtype'] === 'planned' => 'Geplant: '.$item['title'],
        default => $item['title'],
    };
@endphp
<div
    @class([
        'group/dl flex min-w-0 items-center gap-1.5 rounded-[6px] px-1.5 py-1',
        $styles['bg'] => ! $item['isPreview'],
        'border border-dashed border-line' => $item['isPreview'],
    ])
    title="{{ $tooltip }}"
>
    <button
        type="button"
        wire:click="{{ $toggleAction }}({{ $item['id'] }})"
        class="grid h-3 w-3 flex-none place-items-center rounded-full border-[1.5px] {{ $styles['dot'] }} transition hover:scale-110"
        aria-label="Erledigt markieren: {{ $item['title'] }}"
    ></button>

    <span class="min-w-0 flex-1 truncate text-[11px] {{ $item['isPreview'] ? $styles['tx'].' opacity-80' : $styles['tx'] }}">{{ $item['title'] }}</span>

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
