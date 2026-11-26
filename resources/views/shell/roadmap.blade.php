{{-- Step 4.3: the roadmap strip of a project page. The stages in order, each with its name and target
     date. Done = filled dot, now = ringed dot, waiting = faint dot. One rule for every name length and
     number of stages (C30, Niels 2026-11-17: labels under the dots, a long first name cut off and
     overlapping the next one, dates on different lines):
       - phone (below wb): a vertical line, the dot on the left, name and date on the right. A long
         name wraps; nothing scrolls sideways.
       - from wb: one horizontal track over the whole card, the name above its dot (as in the approved
         preview), the date below. Equal columns of at least 7rem, so a name never leaves its column
         and the dots share one line; only a roadmap of more than about seven stages scrolls.
     @param Collection $stages, array<int, string> $states (done | now | waits, one per stage) --}}
@php
    $dots = [
        'done' => 'border-accent bg-accent',
        'now' => 'border-accent bg-accent-soft ring-4 ring-accent-soft',
        'waits' => 'border-ink-faint bg-surface',
    ];
    // A line is drawn in the accent once the project has got to the stage it leads to.
    $line = fn (string $state) => $state === 'waits' ? 'bg-line' : 'bg-accent';
@endphp
<div class="wb:overflow-x-auto">
    <ol data-roadmap class="flex flex-col p-1 wb:grid wb:auto-cols-[minmax(7rem,1fr)] wb:grid-flow-col">
        @foreach ($stages as $i => $s)
            @php $state = $states[$i]; @endphp
            <li wire:key="road-{{ $s->id }}" data-state="{{ $state }}"
                class="grid grid-cols-[1.25rem_minmax(0,1fr)] gap-x-3 wb:grid-cols-1 wb:grid-rows-[1fr_auto_auto] wb:gap-x-0 wb:text-center">
                <span data-roadmap-name class="col-start-2 row-start-1 min-w-0 text-[13px] leading-5 font-semibold text-ink wrap-break-word hyphens-auto wb:col-start-1 wb:self-end wb:px-1 wb:leading-tight">{{ $s->name }}</span>
                <div class="relative col-start-1 row-span-2 row-start-1 flex justify-center wb:row-span-1 wb:row-start-2 wb:h-7 wb:items-center">
                    @if (! $loop->first)
                        <span class="absolute top-1/2 left-0 hidden h-1 w-1/2 -translate-y-1/2 wb:block {{ $line($state) }}" aria-hidden="true"></span>
                    @endif
                    @if (! $loop->last)
                        <span class="absolute top-1/2 right-0 hidden h-1 w-1/2 -translate-y-1/2 wb:block {{ $line($states[$i + 1]) }}" aria-hidden="true"></span>
                        <span class="absolute top-5 bottom-0 left-2 w-1 wb:hidden {{ $line($states[$i + 1]) }}" aria-hidden="true"></span>
                    @endif
                    <span class="relative z-10 grid size-5 shrink-0 place-items-center rounded-full border-[3px] {{ $dots[$state] }}">
                        @if ($state === 'done')@include('shell.icon', ['name' => 'check', 'class' => 'size-3 text-accent-fg'])@endif
                    </span>
                </div>
                <span data-roadmap-date @class(['col-start-2 row-start-2 font-mono text-[10.5px] text-ink-faint wb:col-start-1 wb:row-start-3 wb:px-1 wb:pt-0.5 wb:pb-0', 'pb-5' => ! $loop->last])>{{ $s->target_date ? \App\Support\Dates::padded($s->target_date) : __('projects.no_date') }}</span>
                <span class="sr-only">{{ $state === 'done' ? __('projects.stage_done') : ($state === 'now' ? __('projects.stage_now') : __('projects.stage_waits', ['number' => $i])) }}</span>
            </li>
        @endforeach
    </ol>
</div>
