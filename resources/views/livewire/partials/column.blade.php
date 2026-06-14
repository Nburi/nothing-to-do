{{-- A board column. $list, $title, $count, $rest (collection), and optionally
     $hasToday + $today (collection) for the To-Dos / Tasks columns. --}}
<section class="flex min-h-[55vh] flex-col">
    <header class="mb-3 flex items-center justify-between px-1">
        <h2 class="flex items-center gap-2 text-sm font-medium text-ink">
            {{ $title }}
            <span class="tnum rounded-full bg-surface px-1.5 py-0.5 text-[11px] text-ink-faint">{{ $count }}</span>
        </h2>
    </header>

    @if (($hasToday ?? false))
        <div class="mb-3 rounded-card border border-forest/30 bg-forest-soft/60 p-2">
            <p class="mb-2 flex items-center gap-1.5 px-1 text-[11px] font-medium uppercase tracking-[0.14em] text-forest">
                <x-logo class="h-3 w-3" />
                Heute
            </p>
            <div
                class="flex min-h-[40px] flex-col gap-2"
                data-list="{{ $list }}" data-today="true"
                x-data x-init="window.boardSortable($el, $wire)"
            >
                @foreach ($today as $task)
                    @include('livewire.partials.task-card', ['task' => $task])
                @endforeach
                @if ($today->isEmpty())
                    <p class="px-1 py-1.5 text-xs text-ink-faint">Hierher ziehen für den Tagesfokus.</p>
                @endif
            </div>
        </div>
    @endif

    <div
        class="flex flex-1 flex-col gap-2"
        data-list="{{ $list }}" data-today="false"
        x-data x-init="window.boardSortable($el, $wire)"
    >
        @foreach ($rest as $task)
            @include('livewire.partials.task-card', ['task' => $task])
        @endforeach

        @if ($rest->isEmpty())
            <div class="flex flex-col items-center justify-center gap-2.5 rounded-card border border-dashed border-line px-4 py-10 text-center">
                <svg class="h-9 w-9 text-line" viewBox="0 0 48 48" fill="none" aria-hidden="true">
                    <path d="M24 8c8 0 14 5 14 11s-7 10-14 10S11 25 11 19 16 8 24 8Z" stroke="currentColor" stroke-width="1.5"/>
                    <path d="M24 13.5c5 0 8.5 2.6 8.5 6S29 25 24 25s-8-2-8-5.5 3-6 8-6Z" stroke="currentColor" stroke-width="1.5"/>
                    <circle cx="24" cy="19.5" r="1.8" fill="currentColor"/>
                </svg>
                <p class="max-w-[22ch] text-xs leading-relaxed text-ink-faint">{{ $empty }}</p>
            </div>
        @endif
    </div>
</section>
