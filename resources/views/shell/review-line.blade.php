{{-- Step 4.4: on Sunday and Monday a quiet dashed line leads to the Sunday review. Board mode only (it folds
     away with the board). @param $offer ('sunday' | 'monday' | null), $class (optional) --}}
@if ($offer)
    <a href="{{ route('shell.review') }}" wire:navigate data-review-line
        @class(['group flex items-center gap-3 rounded-card border border-dashed border-line bg-surface px-3.5 py-2.5 text-sm text-ink-soft transition hover:text-ink focus:outline-hidden focus-visible:ring-2 focus-visible:ring-accent', $class ?? ''])>
        @include('shell.icon', ['name' => 'calendar', 'class' => 'size-4 shrink-0 text-ink-faint'])
        <span class="min-w-0 flex-1">{{ __('shell.review.line_'.$offer) }}</span>
        <span class="shrink-0 font-semibold text-accent-ink group-hover:underline underline-offset-4">{{ __('shell.review.line_open') }}</span>
    </a>
@endif
