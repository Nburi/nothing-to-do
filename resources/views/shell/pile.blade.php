{{-- One Stapel pile: its top card, a coloured top edge per list, the count. Tap opens the list.
     @param $list (inbox|todos|tasks|later|done), $top (?Task), $count, $mode --}}
@php
    $edge = ['inbox' => 'var(--t-accent)', 'todos' => 'var(--t-area-2)', 'tasks' => 'var(--t-area-1)', 'later' => 'var(--t-area-3)', 'done' => 'var(--t-good)'][$list];
    $label = $list === 'done' ? trans_choice('shell.done_tonight', $count, ['count' => $count]) : __('shell.lists.'.$list);
    $sub = $top && $list !== 'done' ? \App\Shell\TaskFace::meta($top) : null;
    $pileClass = 'group flex min-w-0 flex-col gap-2 text-left focus:outline-hidden';
@endphp
@if ($list === 'later')
    <x-shell.action key="browse_later" layout="stapel" :mode="$mode" @click="peek = 'later'" class="{{ $pileClass }}">
        @include('shell.pile-face')
    </x-shell.action>
@elseif ($list === 'done')
    <div class="{{ $pileClass }}">@include('shell.pile-face')</div>
@else
    <button type="button" @click="peek = '{{ $list }}'" class="{{ $pileClass }}">@include('shell.pile-face')</button>
@endif
