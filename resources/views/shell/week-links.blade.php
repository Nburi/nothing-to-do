{{-- Step 4.1: entry points from the board to the week plan editor, so "move a block", "pause days" and the week
     itself are one tap away in both layouts and both modes (R7, ActionParityTest).
     @param $layout, $mode, $keys (week_plan | move_event | pause_days), $class --}}
@php
    $links = [
        'week_plan' => [route('shell.week'), 'calendar', __('week.links.week')],
        'move_event' => [route('shell.week'), 'shuffle', __('week.links.move')],
        'pause_days' => [route('shell.week', ['pause' => 1]), 'pause', __('week.links.pause')],
    ];
@endphp
<div class="flex flex-wrap items-center gap-x-3 gap-y-1 {{ $class ?? '' }}">
    @foreach ($keys as $key)
        <x-shell.action :key="$key" :layout="$layout" :mode="$mode" as="a" :href="$links[$key][0]" wire:navigate
            class="inline-flex min-h-8 items-center gap-1.5 rounded-card text-xs font-semibold text-ink-soft transition hover:text-ink focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">
            @include('shell.icon', ['name' => $links[$key][1], 'class' => 'size-3.5'])<span>{{ $links[$key][2] }}</span>
        </x-shell.action>
    @endforeach
</div>
