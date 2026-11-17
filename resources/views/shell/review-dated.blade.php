{{-- Sunday review (step 4.4): tasks with a date, a hard one marked "fix", a soft one "bis" (never red).
     @param $rows (list of title/date/hard), $more (int), $chip (Closure: row => label) --}}
<ul class="flex flex-col">
    @foreach ($rows as $row)
        <li class="flex min-w-0 items-baseline gap-2.5 py-2 text-sm first:pt-0" @if (! $loop->first) style="border-top: var(--t-bw) solid var(--t-line)" @endif>
            <span class="min-w-0 flex-1 wrap-break-word text-ink">{{ $row['title'] }}</span>
            <span @class(['shrink-0 rounded-[5px] border px-1.5 font-mono text-[10.5px]', 'border-ink text-ink' => $row['hard'], 'border-line text-ink-soft' => ! $row['hard']])>{{ $chip($row) }}</span>
        </li>
    @endforeach
</ul>
@if ($more > 0)
    <span class="text-[12.5px] text-ink-faint">{{ __('shell.review.list_more', ['count' => $more]) }}</span>
@endif
