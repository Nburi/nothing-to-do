<?php

namespace App\Livewire\Shell;

use App\Models\EventCategory;
use App\Models\EventTemplate;
use App\Models\ScheduleEvent;
use App\Models\SchedulePause;
use App\Models\User;
use App\Planning\Block;
use App\Planning\ChangeScope;
use App\Planning\SessionSlot;
use App\Planning\SlotCalculator;
use App\Planning\Template;
use App\Planning\WeekPlan;
use App\Services\Planning\WeekPlanEditor;
use App\Services\Planning\WeekPlanJournal;
use App\Services\Planning\WeekPlanReader;
use App\Support\UserClock;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The week plan editor (refactor step 4.1; R4, R7, R10). Two pages like the old app (Niels, 2026-11-17):
 *
 * - **Zeitplan** (/board/week): the real week with dates, what is actually on. Changing a block of
 *   the Wochenplan asks "Nur diese Woche" or "Jede Woche" (two buttons, never a silent default).
 *   Pause days live here: a calendar range in two taps, a day header switches one day off or on.
 * - **Wochenplan** (/board/week/plan): Monday to Sunday without dates, only what repeats. Every
 *   change here is "every week from tomorrow", so there is no question.
 *
 * Both: draw a block by pressing and dragging in the time grid (over several days on desktop,
 * long press on the phone, a short tap there is one hour); drag a block to move it; the form opens
 * as a popover next to the block. Every change is undoable (WeekPlanJournal), never confirmed.
 * All rules are WeekPlanEditor's; this page only collects input and shows WeekPlanReader's result.
 */
#[Layout('layouts.shell')]
class Week extends Component
{
    /** The grid snaps drawn and dragged blocks to this many minutes (Niels' pick). */
    public const SNAP = 15;

    #[Locked]
    public string $view = 'zeit';

    /** Monday of the Zeitplan week, "Y-m-d". */
    #[Url(as: 'w', history: true, except: '')]
    public string $week = '';

    /**
     * The block form (create and edit share it, CONVENTIONS 6), or null.
     * mode: new | occ | once (Zeitplan) · tnew | tpl (Wochenplan)
     *
     * @var array<string, mixed>|null
     */
    public ?array $form = null;

    /** The pause sheet is open (also from the board's "Pause" entry, ?pause=1). */
    public bool $pauseOpen = false;

    public ?string $notice = null;

    /** The journal of the last edit, for "Rückgängig". */
    #[Locked]
    public ?array $undo = null;

    public function mount(?string $view = null): void
    {
        $this->view = $view === 'plan' ? 'plan' : 'zeit';
        $this->week = $this->monday($this->week);
        $this->pauseOpen = $this->view === 'zeit' && request()->boolean('pause');
    }

    #[Computed]
    public function user(): User
    {
        return Auth::user();
    }

    #[Computed]
    public function today(): string
    {
        return UserClock::now($this->user)->toDateString();
    }

    /** @return list<string> Monday to Sunday of the Zeitplan week */
    #[Computed]
    public function dates(): array
    {
        return WeekPlanReader::dates($this->week, CarbonImmutable::parse($this->week)->addDays(6)->toDateString());
    }

    /** @return Collection<int, EventCategory> the user's categories, keyed by id */
    #[Computed]
    public function categories(): Collection
    {
        return EventCategory::forUser($this->user)->with('areas:id')->ordered()->get()->keyBy('id');
    }

    /**
     * The seven columns of the grid: Zeitplan dates or Wochenplan weekdays.
     *
     * @return list<array{key: string, weekday: int, date: ?string, paused: bool, note: ?string, sessions: int, blocks: list<array<string, mixed>>, ghosts: list<array<string, mixed>>}>
     */
    #[Computed]
    public function columns(): array
    {
        return $this->view === 'plan' ? $this->planColumns() : $this->zeitColumns();
    }

    /** True when the user has nothing in the plan at all (empty state, B1). */
    #[Computed]
    public function blank(): bool
    {
        return ! EventTemplate::forUser($this->user)->recurring()->exists()
            && ! ScheduleEvent::forUser($this->user)->whereDate('date', '>=', $this->today)->exists();
    }

    /**
     * Wochenplan side panel: hours and sessions per category per week, and the repeating Termine.
     *
     * @return array{categories: list<array<string, mixed>>, termine: list<array<string, mixed>>}
     */
    #[Computed]
    public function summary(): array
    {
        $byCategory = [];
        $termine = [];
        foreach ($this->columns as $column) {
            foreach ($column['blocks'] as $block) {
                if ($block['category'] === null) {
                    $termine[$block['tid']] ??= ['title' => $block['title'], 'days' => []];
                    $termine[$block['tid']]['days'][] = $column['weekday'];

                    continue;
                }
                $byCategory[$block['category']] ??= ['minutes' => 0, 'sessions' => 0];
                $byCategory[$block['category']]['minutes'] += $block['length'];
                $byCategory[$block['category']]['sessions'] += $block['sessions'];
            }
        }

        return [
            'categories' => $this->categories->map(fn (EventCategory $c) => [
                'name' => $c->name, 'color' => $c->color, 'pomodoro' => $c->pomodoro_enabled,
                'minutes' => $byCategory[$c->id]['minutes'] ?? 0, 'sessions' => $byCategory[$c->id]['sessions'] ?? 0,
            ])->values()->all(),
            'termine' => array_values($termine),
        ];
    }

    // ── navigation ───────────────────────────────────────────────────

    public function goWeek(int $offset): void
    {
        $this->week = $offset === 0
            ? $this->monday('')
            : $this->monday(CarbonImmutable::parse($this->week)->addWeeks(max(-60, min(60, $offset)))->toDateString());
        $this->form = null;
        $this->fresh();
    }

    // ── the form ─────────────────────────────────────────────────────

    /**
     * Drawn, tapped or "+ Block": a new block on these weekdays (1 = Monday) from $start to $end.
     *
     * @param  list<int>  $days
     */
    public function openNew(array $days, string $start, string $end): void
    {
        $days = $this->cleanDays($days) ?: [CarbonImmutable::parse($this->today)->dayOfWeekIso];
        $category = $this->categories->firstWhere('pomodoro_enabled', true) ?? $this->categories->first();

        $this->resetValidation();
        $this->form = [
            'mode' => $this->view === 'plan' ? 'tnew' : 'new',
            'days' => $days,
            'start' => $this->time($start, '18:00'),
            'end' => $this->time($end, '19:00'),
            'kind' => $category ? 'cat' : 'termin',
            'categoryId' => $category?->id,
            'title' => '',
        ];
    }

    /**
     * Click on a block (or a block dragged somewhere else: $toKey is the column it landed in,
     * a date in the Zeitplan, a weekday in the Wochenplan, with its new times).
     */
    public function openBlock(string $kind, int $id, ?string $date = null, ?string $toKey = null, ?string $start = null, ?string $end = null): void
    {
        $block = $this->findBlock($kind, $id, $date);
        if ($block === null) {
            $this->gone();

            return;
        }

        $this->resetValidation();
        $form = [
            'mode' => $kind,
            'id' => $id,
            'date' => $block['date'],
            'newDate' => $block['date'],
            'start' => $block['start'],
            'end' => $block['end'],
            'kind' => $block['category'] === null ? 'termin' : 'cat',
            'categoryId' => $block['category'],
            'title' => $block['category'] === null ? $block['title'] : '',
            'changed' => $block['changed'],
            'moved' => false,
        ];

        if ($kind === 'once') {
            // A block moved here "this week only" can go back to the series it came from.
            $form['movedFrom'] = app(WeekPlanEditor::class)->movedFrom($this->user, $id);
        }

        if ($kind === 'tpl') {
            $template = EventTemplate::forUser($this->user)->find($id);
            $form['days'] = $template?->recurrenceDays() ?? [];
            $form['weekday'] = (int) ($date ?? 0);
            if ($toKey !== null && (int) $toKey !== $form['weekday'] && in_array((int) $toKey, range(1, 7), true)) {
                $form['days'] = $this->cleanDays([...array_diff($form['days'], [$form['weekday']]), (int) $toKey]);
                $form['moved'] = true;
            }
        } elseif ($toKey !== null && in_array($toKey, $this->dates, true) && $toKey !== $block['date']) {
            $form['newDate'] = $toKey;
            $form['moved'] = true;
        }

        if ($start !== null && $end !== null) {
            $form['moved'] = $form['moved'] || $start !== $block['start'];
            $form['start'] = $this->time($start, $block['start']);
            $form['end'] = $this->time($end, $block['end']);
        }

        $this->form = $form;
    }

    /** A weekday chip of the form (1 = Monday). */
    public function toggleDay(int $day): void
    {
        if ($this->form === null || $day < 1 || $day > 7) {
            return;
        }
        $days = $this->form['days'] ?? [];
        $this->form['days'] = $this->cleanDays(in_array($day, $days, true) ? array_diff($days, [$day]) : [...$days, $day]);
    }

    public function closeForm(): void
    {
        $this->form = null;
        $this->resetValidation();
    }

    /** Save the form. $scope: "this" (nur diese Woche) or "every" (jede Woche); only asked in the Zeitplan. */
    public function save(?string $scope = null): void
    {
        $f = $this->form;
        if ($f === null) {
            return;
        }

        $editor = app(WeekPlanEditor::class);
        $category = ($f['kind'] ?? 'cat') === 'cat' ? $this->intOrNull($f['categoryId'] ?? null) : null;
        $title = ($f['kind'] ?? 'cat') === 'termin' ? trim((string) ($f['title'] ?? '')) : null;
        $start = (string) ($f['start'] ?? '');
        $end = (string) ($f['end'] ?? '');
        $every = $scope === 'every';

        if (($f['kind'] ?? 'cat') === 'cat' && $category === null) {
            $this->addError('form.categoryId', __('week.form.pick_category'));

            return;
        }

        $message = $this->attempt(function () use ($editor, $f, $category, $title, $start, $end, $every, $scope) {
            switch ($f['mode']) {
                case 'new':
                    if (! in_array($scope, ['this', 'every'], true)) {
                        return null;
                    }
                    $days = $this->cleanDays($f['days'] ?? []);
                    if ($every) {
                        $editor->addRepeating($this->user, $days, $start, $end, $category, $title);

                        return trans_choice('week.saved.new_every', count($days), ['days' => $this->dayList($days)]);
                    }
                    if ($days === []) {
                        throw ValidationException::withMessages(['weekdays' => __('week.form.pick_day')]);
                    }
                    foreach ($days as $day) {
                        $editor->addOnce($this->user, $this->dates[$day - 1], $start, $end, $category, $title);
                    }

                    return trans_choice('week.saved.new_once', count($days), ['count' => count($days), 'date' => $this->short($this->dates[$days[0] - 1])]);

                case 'tnew':
                    $editor->addRepeating($this->user, $this->cleanDays($f['days'] ?? []), $start, $end, $category, $title);

                    return __('week.saved.plan');

                case 'tpl':
                    $editor->updateTemplate($this->user, (int) $f['id'], $this->cleanDays($f['days'] ?? []), $start, $end, $category, $title);

                    return __('week.saved.plan');

                case 'once':
                    $editor->updateOnce($this->user, (int) $f['id'], (string) $f['newDate'], $start, $end, $category, $title);

                    return __('week.saved.once');

                case 'occ':
                    return $this->saveOccurrence($editor, $f, $category, $start, $end, $scope);
            }

            return null;
        });

        if ($message !== null) {
            $this->form = null;
            $this->notice = $message;
        }
    }

    /** Remove the block of the form ($scope as in save() for an occurrence of the Wochenplan). */
    public function remove(?string $scope = null): void
    {
        $f = $this->form;
        if ($f === null) {
            return;
        }

        $editor = app(WeekPlanEditor::class);
        $message = $this->attempt(fn () => match ($f['mode']) {
            'once' => tap(__('week.removed.once'), fn () => $editor->deleteOnce($this->user, (int) $f['id'])),
            'tpl' => tap(__('week.removed.plan'), fn () => $editor->stopTemplate($this->user, (int) $f['id'])),
            'occ' => match ($scope) {
                'this' => tap(__('week.removed.this', ['date' => $this->short($f['date'])]), fn () => $editor->cancelOccurrence($this->user, (int) $f['id'], $f['date'], ChangeScope::ThisWeekOnly)),
                'every' => tap(__('week.removed.every', ['day' => $this->weekday($f['date'])]), fn () => $editor->cancelOccurrence($this->user, (int) $f['id'], $f['date'], ChangeScope::EveryWeek)),
                default => null,
            },
            default => null,
        });

        if ($message !== null) {
            $this->form = null;
            $this->notice = $message;
        }
    }

    /**
     * "Zurück zum Wochenplan": this date follows the template again (mode occ). A block moved here
     * this week only (mode once) goes back to the date it came from. The server re-reads what the
     * block is; nothing in the form decides it.
     */
    public function restoreOccurrence(): void
    {
        $f = $this->form;
        $mode = $f['mode'] ?? null;
        if (! in_array($mode, ['occ', 'once'], true)) {
            return;
        }

        $editor = app(WeekPlanEditor::class);
        $message = $this->attempt(function () use ($f, $mode, $editor) {
            if ($mode === 'once') {
                $date = $editor->restoreMoved($this->user, (int) $f['id']);
            } else {
                $date = (string) $f['date'];
                $editor->restoreOccurrence($this->user, (int) $f['id'], $date);
            }

            return __('week.saved.restored', ['date' => $this->short($date)]);
        });

        if ($message !== null) {
            $this->form = null;
            $this->notice = $message;
        }
    }

    // ── pause days ───────────────────────────────────────────────────

    /** A click on a day header: switch that day off, or back on. */
    public function togglePause(string $date): void
    {
        if (! in_array($date, $this->dates, true)) {
            return;
        }

        $paused = SchedulePause::forUser($this->user)->forRange($date, $date)->exists();
        $editor = app(WeekPlanEditor::class);
        $this->notice = $this->attempt(fn () => $paused
            ? tap(__('week.pause.resumed', ['day' => $this->weekday($date).' '.$this->short($date)]), fn () => $editor->resume($this->user, $date))
            : tap(__('week.pause.paused_day', ['day' => $this->weekday($date).' '.$this->short($date)]), fn () => $editor->pause($this->user, $date, $date)));
    }

    /** The pause sheet: a range from the calendar (two taps) and an optional note. */
    public function pauseRange(string $from, string $to, ?string $note = null): void
    {
        $note = $note === null ? null : (trim($note) ?: null);
        $message = $this->attempt(function () use ($from, $to, $note) {
            app(WeekPlanEditor::class)->pause($this->user, $from, $to, $note);

            return __('week.pause.paused_range', ['from' => $this->short($from), 'to' => $this->short($to), 'count' => count(WeekPlanReader::dates($from, $to))]);
        }, 'pause');

        if ($message !== null) {
            $this->pauseOpen = false;
            $this->notice = $message;
            $this->week = $this->monday($from);
            $this->fresh();
        }
    }

    /** A ghost of a paused day: bring this block back for that day only (an exception to the pause). */
    public function restoreGhost(int $templateId, string $date): void
    {
        if (! in_array($date, $this->dates, true)) {
            return;
        }

        $message = $this->attempt(function () use ($templateId, $date) {
            $row = app(WeekPlanEditor::class)->restoreGhost($this->user, $templateId, $date);

            return __('week.ghost.restored', ['title' => $row->displayTitle(), 'day' => $this->weekday($date).' '.$this->short($date)]);
        });

        if ($message !== null) {
            $this->notice = $message;
        }
    }

    public function openPause(): void
    {
        $this->form = null;
        $this->resetValidation();
        $this->pauseOpen = true;
    }

    public function closePause(): void
    {
        $this->pauseOpen = false;
        $this->resetValidation();
    }

    // ── undo ─────────────────────────────────────────────────────────

    public function undoLast(): void
    {
        if ($this->undo === null) {
            $this->notice = null;

            return;
        }

        WeekPlanJournal::undo($this->user, $this->undo);
        $this->undo = null;
        $this->notice = __('week.undone');
        $this->fresh();
    }

    public function dismiss(): void
    {
        $this->notice = null;
        $this->undo = null;
    }

    public function render(): View
    {
        return view('livewire.shell.week')->title(__($this->view === 'plan' ? 'week.plan.title' : 'week.zeit.title').' · nothing-to-do');
    }

    // ── internals ────────────────────────────────────────────────────

    /**
     * Run one edit through the journal. A validation error lands on the form's fields
     * ("form.start", or "pause.to" for the pause sheet); a vanished row says so calmly.
     */
    private function attempt(\Closure $op, string $errorBag = 'form'): ?string
    {
        $this->resetValidation();

        try {
            [$message, $journal] = WeekPlanJournal::record($op);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                $field = match (true) {
                    str_starts_with($key, 'weekdays') => 'days',
                    $key === 'date' => 'newDate',
                    default => $key,
                };
                $this->addError($errorBag.'.'.$field, $messages[0]);
            }

            return null;
        } catch (ModelNotFoundException) {
            $this->gone();

            return null;
        }

        if ($message !== null) {
            $this->undo = $journal === [] ? null : $journal;
        }
        $this->fresh();

        return $message;
    }

    private function saveOccurrence(WeekPlanEditor $editor, array $f, ?int $category, string $start, string $end, ?string $scope): ?string
    {
        $id = (int) $f['id'];
        $date = (string) $f['date'];
        $newDate = in_array($f['newDate'] ?? null, $this->dates, true) ? $f['newDate'] : $date;
        $template = EventTemplate::forUser($this->user)->findOrFail($id);
        $category = $template->category_id === null && $category === null ? null : $category;

        if ($scope === 'this') {
            if ($newDate !== $date) {
                $editor->moveOccurrence($this->user, $id, $date, $newDate, $start, $end);
            } else {
                $editor->changeOccurrence($this->user, $id, $date, $start, $end, $category, ChangeScope::ThisWeekOnly);
            }

            return __('week.saved.this', ['date' => $this->short($date)]);
        }

        if ($scope !== 'every') {
            return null;
        }

        if ($newDate !== $date) {
            // Every week on another weekday: the Wochenplan block moves from one weekday to the other.
            $from = CarbonImmutable::parse($date)->dayOfWeekIso;
            $to = CarbonImmutable::parse($newDate)->dayOfWeekIso;
            $days = $this->cleanDays([...array_diff($template->recurrenceDays(), [$from]), $to]);
            $editor->updateTemplate($this->user, $id, $days, $start, $end, $category ?? $template->category_id, $category === null && $template->category_id === null ? $template->name : null);
        } else {
            $editor->changeOccurrence($this->user, $id, $date, $start, $end, $category ?? $template->category_id, ChangeScope::EveryWeek);
        }

        return __('week.saved.every', ['day' => $this->weekday($newDate)]);
    }

    private function zeitColumns(): array
    {
        $reader = new WeekPlanReader;
        [$from, $to] = [$this->dates[0], $this->dates[6]];
        $categories = $reader->categories($this->user);
        $blocks = $reader->blocks($this->user, $from, $to);
        $ghosts = $reader->ghosts($this->user, $from, $to);
        $calculator = new SlotCalculator($categories);
        $pauses = SchedulePause::forUser($this->user)->forRange($from, $to)->get()
            ->mapWithKeys(fn (SchedulePause $p) => [$p->date->toDateString() => (string) $p->note]);

        $columns = [];
        foreach ($this->dates as $date) {
            $slots = $calculator->slots($blocks[$date]);
            $columns[] = [
                'key' => $date,
                'weekday' => CarbonImmutable::parse($date)->dayOfWeekIso,
                'date' => $date,
                'paused' => $pauses->has($date),
                'note' => $pauses->get($date) ?: null,
                'sessions' => count($slots),
                'blocks' => $this->lanes(array_map(fn (Block $b) => $this->blockArray($b, $slots, $b->templateId !== null ? 'occ' : 'once'), $blocks[$date])),
                // What a paused day would have had: shown pale, never counted (B6).
                'ghosts' => $this->lanes(array_map(fn (Block $b) => $this->blockArray($b, [], 'ghost'), $ghosts[$date] ?? [])),
            ];
        }

        return $columns;
    }

    private function planColumns(): array
    {
        $templates = EventTemplate::forUser($this->user)->recurring()->with('category')->get();
        $reader = new WeekPlanReader;
        $timezone = UserClock::timezone($this->user, CarbonImmutable::parse($this->week));
        // The Wochenplan on its own (no one-week changes, no pauses), read through the engine so
        // the session counts are the same numbers the suggestion and the early warning use.
        $plan = new WeekPlan($timezone, $templates->map(fn (EventTemplate $t) => new Template(
            $t->id, $t->recurrenceDays(), $t->default_start ?: '08:00', (int) $t->duration, $t->category_id, $t->displayName(),
        ))->values()->all());
        $calculator = new SlotCalculator($reader->categories($this->user));

        $columns = [];
        foreach ($this->referenceDates() as $date) {
            $blocks = $plan->blocksOn($date);
            $slots = $calculator->slots($blocks);
            $weekday = CarbonImmutable::parse($date)->dayOfWeekIso;
            $columns[] = [
                'key' => (string) $weekday,
                'weekday' => $weekday,
                'date' => null,
                'paused' => false,
                'note' => null,
                'sessions' => count($slots),
                'ghosts' => [],
                'blocks' => $this->lanes(array_map(fn (Block $b) => ['date' => (string) $weekday] + $this->blockArray($b, $slots, 'tpl'), $blocks)),
            ];
        }

        return $columns;
    }

    /** @param  list<SessionSlot>  $slots */
    private function blockArray(Block $block, array $slots, string $kind): array
    {
        $category = $block->categoryId === null ? null : $this->categories->get($block->categoryId);
        $start = $block->start->format('H:i');
        $end = $block->end->format('H:i');
        $length = ScheduleEvent::toMinutes($end) - ScheduleEvent::toMinutes($start);
        if ($length <= 0) {
            $length = 1440 - ScheduleEvent::toMinutes($start);
        }

        return [
            'kind' => $kind,
            'id' => $kind === 'once' ? $block->eventId : $block->templateId,
            'tid' => $block->templateId,
            'date' => $block->date,
            'start' => $start,
            'end' => $end,
            'top' => ScheduleEvent::toMinutes($start),
            'length' => $length,
            'title' => $category?->name ?? ($block->title ?: __('week.form.untitled')),
            'category' => $category?->id,
            'color' => $category?->color,
            'pomodoro' => (bool) $category?->pomodoro_enabled,
            'sessions' => count(array_filter($slots, fn (SessionSlot $s) => $s->categoryId === $block->categoryId
                && $s->start->gte($block->start) && $s->start->lt($block->end))),
            'changed' => $block->changed,
        ];
    }

    /**
     * Overlapping blocks stand side by side: each gets a lane and the number of lanes of its
     * cluster (blocks that overlap each other directly or through a chain).
     *
     * @param  list<array<string, mixed>>  $blocks  sorted by start
     * @return list<array<string, mixed>>
     */
    private function lanes(array $blocks): array
    {
        $out = [];
        $cluster = [];
        $laneEnds = [];
        $clusterEnd = -1;

        $flush = function () use (&$out, &$cluster, &$laneEnds) {
            foreach ($cluster as $block) {
                $out[] = $block + ['lanes' => max(1, count($laneEnds))];
            }
            $cluster = [];
            $laneEnds = [];
        };

        foreach ($blocks as $block) {
            $end = $block['top'] + $block['length'];
            if ($block['top'] >= $clusterEnd) {
                $flush();
            }
            $lane = 0;
            while (isset($laneEnds[$lane]) && $laneEnds[$lane] > $block['top']) {
                $lane++;
            }
            $laneEnds[$lane] = $end;
            $cluster[] = $block + ['lane' => $lane];
            $clusterEnd = max($clusterEnd, $end);
        }
        $flush();

        return $out;
    }

    /** The block the user clicked, read back from the grid so the form shows what the grid shows. */
    private function findBlock(string $kind, int $id, ?string $key): ?array
    {
        if (! in_array($kind, $this->view === 'plan' ? ['tpl'] : ['occ', 'once'], true)) {
            return null;
        }

        foreach ($this->columns as $column) {
            if ($key !== null && $column['key'] !== $key) {
                continue;
            }
            foreach ($column['blocks'] as $block) {
                if ($block['kind'] === $kind && $block['id'] === $id) {
                    return $block;
                }
            }
        }

        return null;
    }

    /** @return list<string> a Monday-to-Sunday week the Wochenplan is read on (the Zeitplan's week) */
    private function referenceDates(): array
    {
        return $this->dates;
    }

    private function monday(?string $date): string
    {
        $today = CarbonImmutable::parse($this->today);
        try {
            $day = $date !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? CarbonImmutable::parse($date) : $today;
        } catch (\Throwable) {
            $day = $today;
        }
        if (abs($day->diffInDays($today)) > 800) {
            $day = $today;
        }

        return $day->startOfWeek(CarbonImmutable::MONDAY)->toDateString();
    }

    /** @return list<int> */
    private function cleanDays(array $days): array
    {
        $days = array_values(array_unique(array_filter(array_map('intval', $days), fn (int $d) => $d >= 1 && $d <= 7)));
        sort($days);

        return $days;
    }

    private function time(string $value, string $fallback): string
    {
        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) ? $value : $fallback;
    }

    private function intOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    public function short(string $date): string
    {
        return CarbonImmutable::parse($date)->format('j.n.');
    }

    public function weekday(string $date): string
    {
        return __('week.days_long.'.CarbonImmutable::parse($date)->dayOfWeekIso);
    }

    /** @param  list<int>  $days */
    private function dayList(array $days): string
    {
        return implode(', ', array_map(fn (int $d) => __('week.days.'.$d), $days));
    }

    private function gone(): void
    {
        $this->form = null;
        $this->notice = __('week.gone');
        $this->undo = null;
        $this->fresh();
    }

    private function fresh(): void
    {
        unset($this->columns, $this->dates, $this->blank, $this->summary, $this->categories);
    }
}
