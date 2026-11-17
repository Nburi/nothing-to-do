{{-- display: contents, so the row's own scroll wrapper is the direct flex child
     of the header's action group exactly as before this became a component —
     and an empty row leaves no footprint at all (see App\Livewire\HeaderBadgeRow). --}}
<div class="contents" data-header-badges>
    @if (count($badges) > 0)
        {{-- max-w-[45vw]: leaves the wordmark-free mobile header enough room before this
             falls back to its own horizontal scroll — the safety net for a wide badge
             selection on a narrow phone (same pattern as the homework preview strip). --}}
        <div class="flex max-w-[45vw] items-center gap-1.5 overflow-x-auto sm:max-w-none">
            @foreach ($badges as $badge)
                @include('partials.header-badge', ['badge' => $badge])
            @endforeach
        </div>
    @endif
</div>
