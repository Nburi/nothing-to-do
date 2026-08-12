{{--
    One day's worth of deadline chips for the Zeitplan's all-day strip. Shows the first 2, then
    (mirroring the "N weitere · Alle anzeigen" disclosure used elsewhere — Notfallmodus,
    Bastelideen) a client-only Alpine toggle for the rest, so a busy day never blows out the row
    height for the whole week.
--}}
@php
    $visible = $items->take(2);
    $rest = $items->slice(2);
@endphp
@if ($items->isNotEmpty())
    <div class="flex flex-col gap-1" @if ($rest->isNotEmpty()) x-data="{ show: false }" @endif>
        @foreach ($visible as $item)
            @include('livewire.partials.schedule-deadline-item', ['item' => $item])
        @endforeach

        @if ($rest->isNotEmpty())
            <div x-show="show" x-cloak class="flex flex-col gap-1">
                @foreach ($rest as $item)
                    @include('livewire.partials.schedule-deadline-item', ['item' => $item])
                @endforeach
            </div>
            <button
                type="button"
                @click="show = ! show"
                x-text="show ? 'weniger' : '+{{ $rest->count() }} weitere'"
                class="text-left text-[10px] text-ink-faint transition hover:text-ink-soft"
            >+{{ $rest->count() }} weitere</button>
        @endif
    </div>
@endif
