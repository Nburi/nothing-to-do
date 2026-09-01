<?php

namespace App\Livewire;

use App\Livewire\Concerns\ManagesModuleSettings;
use App\Models\AgendaEntry;
use App\Models\CategoryAttribute;
use App\Models\EventCategory;
use App\Models\PushSubscription;
use App\Models\ScheduleEvent;
use App\Models\Task;
use App\Services\HeaderBadges;
use App\Services\ListConcepts;
use App\Services\PushNotifier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Settings extends Component
{
    use ManagesModuleSettings;

    public string $resetTime = '01:00';

    // Pomodoro rhythm
    public int $pWork = 25;

    public int $pShortBreak = 5;

    public int $pLongBreak = 15;

    public int $pLongEvery = 4;

    public bool $pAutostart = false;

    // Vorschau auf fällige Termine (Deadlines/Wunschtermine/Hausaufgaben/Prüfungen im Zeitplan)
    public bool $deadlinePreviewEnabled = true;

    public int $deadlinePreviewDays = 2;

    // Hausaufgaben-Vorschau im Dashboard
    public bool $homeworkPreviewEnabled = true;

    // Planer (automatische Wochenplanung)
    public bool $plannerEnabled = false;

    // Timezone
    public float $timezoneOffset = 0;

    public bool $timezoneAutoDst = false;

    // Vorbereitung
    public string $prepareTimeOfDay = 'evening';

    public string $prepareReminderMode = 'off';

    public ?string $prepareReminderTime = null;

    // Fortschritt
    public int $dailyTaskGoal = 5;

    public bool $notifyDailyReminder = false;

    public string $dailyReminderTime = '19:00';

    public bool $notifyStreakRisk = false;

    // Listen-Konzept
    public string $listConcept = 'three_things';

    // Add-category form
    public string $newCategoryName = '';

    public string $newCategoryColor = 'contour';

    // Category task-link sheet (which category, if any, is being edited)
    public ?int $linkingCategoryId = null;

    public string $linkTextDraft = '';

    public string $linkTaskSearch = '';

    // Category attributes sheet (which category, if any, is being edited)
    public ?int $managingAttributesCategoryId = null;

    public ?int $editingAttributeId = null;

    public string $attrName = '';

    public string $attrType = 'text';

    public string $attrUnit = '';

    /** @var array<int, array{label: string, color: string}> */
    public array $attrOptions = [];

    public function mount(): void
    {
        $user = auth()->user();

        $this->resetTime = $user->task_reset_time ?? '01:00';
        $this->pWork = $user->pomodoro_work ?? 25;
        $this->pShortBreak = $user->pomodoro_short_break ?? 5;
        $this->pLongBreak = $user->pomodoro_long_break ?? 15;
        $this->pLongEvery = $user->pomodoro_long_every ?? 4;
        $this->pAutostart = $user->pomodoro_autostart ?? false;
        $this->deadlinePreviewEnabled = $user->deadline_preview_enabled ?? true;
        $this->deadlinePreviewDays = $user->deadline_preview_days ?? 2;
        $this->homeworkPreviewEnabled = $user->homework_preview_enabled ?? true;
        $this->plannerEnabled = (bool) $user->planner_enabled;
        $this->timezoneOffset = (float) ($user->timezone_offset ?? 0);
        $this->timezoneAutoDst = $user->timezone_auto_dst ?? false;
        $this->prepareTimeOfDay = $user->prepare_time_of_day ?? 'evening';
        $this->prepareReminderMode = $user->prepare_reminder_mode ?? 'off';
        $this->prepareReminderTime = $user->prepare_reminder_time;
        $this->dailyTaskGoal = $user->dailyTaskGoal();
        $this->notifyDailyReminder = (bool) $user->notify_daily_reminder;
        $this->dailyReminderTime = $user->daily_reminder_time ?? '19:00';
        $this->notifyStreakRisk = (bool) $user->notify_streak_risk;
        $this->listConcept = ListConcepts::for($user);
    }

    /** Autosaves on change — see the "Erledigte Aufgaben" card. */
    public function save(): void
    {
        $data = $this->validate([
            'resetTime' => ['required', 'date_format:H:i'],
        ]);

        auth()->user()->update(['task_reset_time' => $data['resetTime']]);
    }

    /** Autosaves on change — see the "Pomodoro" card. Autostart is a separate immediate toggle, togglePomodoroAutostart(). */
    public function saveSchedule(): void
    {
        $data = $this->validate([
            'pWork' => ['required', 'integer', 'min:1'],
            'pShortBreak' => ['required', 'integer', 'min:1'],
            'pLongBreak' => ['required', 'integer', 'min:1'],
            'pLongEvery' => ['required', 'integer', 'min:1'],
        ]);

        auth()->user()->update([
            'pomodoro_work' => $data['pWork'],
            'pomodoro_short_break' => $data['pShortBreak'],
            'pomodoro_long_break' => $data['pLongBreak'],
            'pomodoro_long_every' => $data['pLongEvery'],
        ]);
    }

    public function togglePomodoroAutostart(): void
    {
        $user = auth()->user();
        $user->update(['pomodoro_autostart' => ! $user->pomodoro_autostart]);
        $this->pAutostart = (bool) $user->pomodoro_autostart;
    }

    /** Immediate-save toggle, like plannerEnabled/homeworkPreviewEnabled — see the "Vorschau auf Termine" card. */
    public function toggleDeadlinePreviewEnabled(): void
    {
        $user = auth()->user();
        $enabled = ! $user->deadline_preview_enabled;
        $user->update(['deadline_preview_enabled' => $enabled]);
        $this->deadlinePreviewEnabled = $enabled;
    }

    /**
     * Autosaves on change. How many days ahead a hard deadline/exam/homework shows an
     * advance-preview entry in the Zeitplan, on top of appearing on its actual date. A soft
     * Wunschtermin never previews (see Task::effectiveIsHard() / Schedule::deadlineItems()) —
     * this only governs how far the preview reaches, not which items are eligible for one.
     */
    public function saveDeadlinePreviewDays(): void
    {
        $data = $this->validate([
            'deadlinePreviewDays' => ['required', 'integer', 'min:0', 'max:14'],
        ]);

        auth()->user()->update(['deadline_preview_days' => $data['deadlinePreviewDays']]);
    }

    /** Which day the Vorbereitung ritual targets — an immediate-save choice, like a category's colour swatch. */
    public function setPrepareTimeOfDay(string $value): void
    {
        if (! in_array($value, ['morning', 'evening'], true)) {
            return;
        }

        $this->prepareTimeOfDay = $value;
        auth()->user()->update(['prepare_time_of_day' => $value]);
    }

    /** off | automatic | fixed — also an immediate-save choice. */
    public function setPrepareReminderMode(string $value): void
    {
        if (! in_array($value, ['off', 'automatic', 'fixed'], true)) {
            return;
        }

        $this->prepareReminderMode = $value;
        auth()->user()->update(['prepare_reminder_mode' => $value]);
    }

    /** Only relevant in "fixed" reminder mode — saves on change, no separate submit button. */
    public function savePrepareReminderTime(): void
    {
        $data = $this->validate([
            'prepareReminderTime' => ['nullable', 'date_format:H:i'],
        ]);

        auth()->user()->update(['prepare_reminder_time' => $data['prepareReminderTime']]);
    }

    /** Autosaves on change. How many tasks completed in one day counts as "hit the daily goal" — drives the progress ring and one of the two celebrations. */
    public function saveDailyGoal(): void
    {
        $data = $this->validate([
            'dailyTaskGoal' => ['required', 'integer', 'min:1', 'max:30'],
        ]);

        auth()->user()->update(['daily_task_goal' => $data['dailyTaskGoal']]);
    }

    /** Evening push if today still has open "Heute"-flagged tasks — an immediate-save toggle like the notify_* rows below. */
    public function toggleNotifyDailyReminder(): void
    {
        $user = auth()->user();
        $user->update(['notify_daily_reminder' => ! $user->notify_daily_reminder]);
        $this->notifyDailyReminder = (bool) $user->notify_daily_reminder;
    }

    /** Only relevant while the reminder above is on — saves on change, no separate submit button (mirrors savePrepareReminderTime). */
    public function saveDailyReminderTime(): void
    {
        $data = $this->validate([
            'dailyReminderTime' => ['required', 'date_format:H:i'],
        ]);

        auth()->user()->update(['daily_reminder_time' => $data['dailyReminderTime']]);
    }

    /** "Last call" push at User::STREAK_RISK_DUE_TIME if the streak would otherwise break today. */
    public function toggleNotifyStreakRisk(): void
    {
        $user = auth()->user();
        $user->update(['notify_streak_risk' => ! $user->notify_streak_risk]);
        $this->notifyStreakRisk = (bool) $user->notify_streak_risk;
    }

    // ── Listen-Konzept ──────────────────────────────────────────────────

    /**
     * @return list<array{key: string, label: string, description: string, available: bool, current: bool}>
     */
    #[Computed]
    public function listConceptRows(): array
    {
        return ListConcepts::rowsFor(auth()->user());
    }

    /**
     * Shared real-data preview behind every available concept's pill — see
     * ListConcepts::previewTasksFor() and the "Listen-Konzept" card. Fetched
     * once per request regardless of how many pills render one.
     *
     * @return Collection<int, Task>
     */
    #[Computed]
    public function listConceptPreviewTasks(): Collection
    {
        return ListConcepts::previewTasksFor(auth()->user());
    }

    /** Immediate-save pick, like Startseite — only a currently-available concept can actually be chosen. */
    public function setListConcept(string $key): void
    {
        if (! ListConcepts::isValid($key)) {
            return;
        }

        $this->listConcept = $key;
        auth()->user()->update(['list_concept' => $key]);
    }

    // ── Header badges ─────────────────────────────────────────────────

    /**
     * Every catalog badge, in the user's current order, with its enabled
     * flag — the "Header-Badges" card's drag/toggle list. See
     * HeaderBadges::preferenceRowsFor() for the merge/default logic.
     *
     * @return Collection<int, array{key: string, enabled: bool, label: string}>
     */
    #[Computed]
    public function headerBadgeRows(): Collection
    {
        return collect(HeaderBadges::preferenceRowsFor(auth()->user()))
            ->map(fn (array $row) => $row + ['label' => HeaderBadges::CATALOG[$row['key']]['label']]);
    }

    public function toggleHeaderBadge(string $key): void
    {
        $rows = HeaderBadges::preferenceRowsFor(auth()->user());

        $rows = array_map(
            fn (array $row) => $row['key'] === $key ? [...$row, 'enabled' => ! $row['enabled']] : $row,
            $rows,
        );

        auth()->user()->update(['header_badges' => $rows]);
        unset($this->headerBadgeRows);
    }

    /**
     * @param  list<string>  $order  every catalog key, in the new order — from the drag handler
     */
    public function reorderHeaderBadges(array $order): void
    {
        $current = collect(HeaderBadges::preferenceRowsFor(auth()->user()))->keyBy('key');

        $rows = collect($order)
            ->filter(fn ($key) => $current->has($key))
            ->map(fn ($key) => $current->get($key))
            ->values()
            ->all();

        // Anything the drag handler didn't report (shouldn't happen — every
        // row is draggable — but never silently drop a badge) stays, appended.
        foreach ($current as $key => $row) {
            if (! in_array($key, $order, true)) {
                $rows[] = $row;
            }
        }

        auth()->user()->update(['header_badges' => $rows]);
        unset($this->headerBadgeRows);
    }

    /** The presence toggle only means something to someone who is in a class. */
    #[Computed]
    public function inAnyAgendaSpace(): bool
    {
        return auth()->user()->agendaSpaces()->exists();
    }

    /**
     * Turning presence off also clears the last recorded timestamp — opting out
     * means "stop tracking me", not just "stop showing it" (see
     * User::touchPresence()).
     */
    public function toggleShowPresence(): void
    {
        $user = auth()->user();
        $enabled = ! $user->show_presence;

        $user->update([
            'show_presence' => $enabled,
            'last_seen_at' => $enabled ? $user->last_seen_at : null,
        ]);
    }

    /** The dashboard's "bald fällige Hausaufgaben" card — an immediate-save toggle like presence. */
    public function toggleHomeworkPreviewEnabled(): void
    {
        $user = auth()->user();
        $enabled = ! $user->homework_preview_enabled;

        $user->update(['homework_preview_enabled' => $enabled]);
        $this->homeworkPreviewEnabled = $enabled;
    }

    /** Default off (see CLAUDE.md) — an automatic planner is exactly the kind of thing that can feel intrusive unasked-for, so it opts in rather than lighting up for everyone. */
    public function togglePlannerEnabled(): void
    {
        $user = auth()->user();
        $enabled = ! $user->planner_enabled;

        $user->update(['planner_enabled' => $enabled]);
        $this->plannerEnabled = $enabled;
    }

    public function toggleNotifyEventStart(): void
    {
        $user = auth()->user();
        $user->update(['notify_event_start' => ! $user->notify_event_start]);
    }

    public function toggleNotifyPomoStart(): void
    {
        $user = auth()->user();
        $user->update(['notify_pomo_start' => ! $user->notify_pomo_start]);
    }

    public function toggleNotifyBreakStart(): void
    {
        $user = auth()->user();
        $user->update(['notify_break_start' => ! $user->notify_break_start]);
    }

    public function toggleNotifyEventUpcoming(): void
    {
        $user = auth()->user();
        $user->update(['notify_event_upcoming' => ! $user->notify_event_upcoming]);
    }

    /** Persists a browser's Web Push subscription so the server can notify it even with every tab closed. */
    public function subscribeToPush(string $endpoint, string $p256dh, string $authToken): void
    {
        $data = validator(
            ['endpoint' => $endpoint, 'p256dh' => $p256dh, 'authToken' => $authToken],
            [
                'endpoint' => ['required', 'url:https', function (string $attribute, mixed $value, \Closure $fail) {
                    if (! $this->isPublicHttpsHost($value)) {
                        $fail('The endpoint must be a public push-service URL.');
                    }
                }],
                'p256dh' => ['required', 'string'],
                'authToken' => ['required', 'string'],
            ],
        )->validate();

        PushSubscription::storeFor(auth()->user(), $data['endpoint'], $data['p256dh'], $data['authToken'], request()->userAgent());
    }

    /** Removes this browser's subscription — the server stops pushing to it. */
    public function unsubscribeFromPush(string $endpoint): void
    {
        auth()->user()->pushSubscriptions()->where('endpoint_hash', PushSubscription::hashEndpoint($endpoint))->delete();
    }

    /**
     * @var list<array{endpoint: string, user_agent: ?string, success: bool, status: ?int, reason: string}>
     */
    public array $testPushResults = [];

    public bool $testPushSent = false;

    /**
     * Sends a real push to every one of the user's devices right now and reports back exactly
     * what happened per device — independent of the notify_* toggles above, since the point is
     * diagnosing delivery itself (VAPID config, network/TLS, a push service rejecting the
     * request), not re-testing which moments are configured to notify.
     */
    public function sendTestPush(): void
    {
        $this->testPushResults = app(PushNotifier::class)->sendDebug(auth()->user(), [
            'title' => 'Test-Benachrichtigung',
            'body' => 'Wenn du das siehst, funktionieren Push-Benachrichtigungen auf diesem Gerät.',
            'url' => '/app/settings',
        ]);
        $this->testPushSent = true;
    }

    /**
     * Defence-in-depth against SSRF: the server later makes a real, VAPID-signed outbound HTTP request to
     * whatever endpoint is stored (PushNotifier -> minishlink/web-push), so a tampered client can't be
     * allowed to point that request at an internal/loopback host by supplying a crafted endpoint here — a
     * genuine browser-issued push endpoint is always a public host on a real push service. This only
     * catches IP-literal hosts, not a hostname that resolves to a private address later (DNS rebinding) —
     * an accepted residual risk given this app's scale.
     */
    private function isPublicHttpsHost(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        if ($host === null || $host === '' || strtolower($host) === 'localhost') {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        }

        return true;
    }

    /** Autosaves on change — see the "Zeitzone" card. The DST toggle saves separately, toggleTimezoneAutoDst(). */
    public function saveTimezone(): void
    {
        $data = $this->validate([
            'timezoneOffset' => ['required', 'numeric', 'between:-12,14'],
        ]);

        auth()->user()->update(['timezone_offset' => $data['timezoneOffset']]);
    }

    public function toggleTimezoneAutoDst(): void
    {
        $user = auth()->user();
        $enabled = ! $user->timezone_auto_dst;
        $user->update(['timezone_auto_dst' => $enabled]);
        $this->timezoneAutoDst = $enabled;
    }

    /**
     * Fills both timezone fields from the browser's own detection
     * (window.detectTimezoneDefaults() in app.js) and saves immediately.
     * Always an explicit click, never run automatically on page load — silently
     * overwriting a deliberately-chosen offset (e.g. someone keeping "home" time
     * while travelling) would be a surprising, hard-to-notice side effect.
     */
    public function applyDetectedTimezone(float $offset, bool $autoDst): void
    {
        $offset = max(-12, min(14, round($offset * 4) / 4));

        auth()->user()->update([
            'timezone_offset' => $offset,
            'timezone_auto_dst' => $autoDst,
        ]);

        $this->timezoneOffset = $offset;
        $this->timezoneAutoDst = $autoDst;
    }

    /** The user's categories, for the settings list. */
    #[Computed]
    public function categories(): Collection
    {
        return auth()->user()->eventCategories()->withCount('customAttributes')->ordered()->get();
    }

    public function addCategory(): void
    {
        $this->newCategoryName = trim($this->newCategoryName);

        $data = $this->validate([
            'newCategoryName' => ['required', 'string', 'max:255'],
            'newCategoryColor' => ['required', Rule::in(ScheduleEvent::EVENT_COLORS)],
        ]);

        auth()->user()->eventCategories()->create([
            'name' => $data['newCategoryName'],
            'color' => $data['newCategoryColor'],
            'sort_order' => auth()->user()->eventCategories()->count(),
        ]);

        $this->reset(['newCategoryName', 'newCategoryColor']);
    }

    public function renameCategory(int $id, string $name): void
    {
        $name = trim($name);

        if ($name === '' || mb_strlen($name) > 255) {
            return;
        }

        auth()->user()->eventCategories()->whereKey($id)->update(['name' => $name]);
    }

    public function setCategoryColor(int $id, string $color): void
    {
        if (! in_array($color, ScheduleEvent::EVENT_COLORS, true)) {
            return;
        }

        auth()->user()->eventCategories()->whereKey($id)->update(['color' => $color]);
    }

    public function toggleCategoryPomodoro(int $id): void
    {
        $category = auth()->user()->eventCategories()->findOrFail($id);
        $category->update(['pomodoro_enabled' => ! $category->pomodoro_enabled]);
    }

    public function deleteCategory(int $id): void
    {
        auth()->user()->eventCategories()->whereKey($id)->delete();
    }

    // ── Category attributes (custom fields, e.g. Trainingstyp/Dauer) ─────

    /** Opens the attributes sheet for one category, in "add new" state. */
    public function manageAttributes(int $id): void
    {
        auth()->user()->eventCategories()->findOrFail($id);

        $this->managingAttributesCategoryId = $id;
        $this->startAddAttribute();
    }

    public function closeAttributes(): void
    {
        $this->reset(['managingAttributesCategoryId', 'editingAttributeId', 'attrName', 'attrType', 'attrUnit', 'attrOptions']);
    }

    /** The category currently open in the attributes sheet, with its attributes eager-loaded. */
    #[Computed]
    public function managingAttributesCategory(): ?EventCategory
    {
        if ($this->managingAttributesCategoryId === null) {
            return null;
        }

        return auth()->user()->eventCategories()->with('customAttributes')->find($this->managingAttributesCategoryId);
    }

    /** Resets the inline form to "add a new attribute", empty. */
    public function startAddAttribute(): void
    {
        $this->editingAttributeId = null;
        $this->attrName = '';
        $this->attrType = 'text';
        $this->attrUnit = '';
        $this->attrOptions = [];
    }

    public function startEditAttribute(int $id): void
    {
        $category = $this->managingAttributesCategory;
        $attribute = $category?->customAttributes->firstWhere('id', $id);

        if ($attribute === null) {
            return;
        }

        $this->editingAttributeId = $attribute->id;
        $this->attrName = $attribute->name;
        $this->attrType = $attribute->type;
        $this->attrUnit = (string) $attribute->unit;
        $this->attrOptions = $attribute->optionsList();
    }

    public function addAttrOptionRow(): void
    {
        $this->attrOptions[] = ['label' => '', 'color' => CategoryAttribute::OPTION_COLORS[0]];
    }

    public function removeAttrOptionRow(int $index): void
    {
        unset($this->attrOptions[$index]);
        $this->attrOptions = array_values($this->attrOptions);
    }

    public function setAttrOptionColor(int $index, string $color): void
    {
        if (! isset($this->attrOptions[$index]) || ! in_array($color, CategoryAttribute::OPTION_COLORS, true)) {
            return;
        }

        $this->attrOptions[$index]['color'] = $color;
    }

    public function saveAttribute(): void
    {
        $category = auth()->user()->eventCategories()->findOrFail($this->managingAttributesCategoryId);

        $data = $this->validate([
            'attrName' => ['required', 'string', 'max:255'],
            'attrType' => ['required', Rule::in(array_keys(CategoryAttribute::TYPES))],
            'attrUnit' => ['nullable', 'string', 'max:32'],
        ]);

        $options = null;

        if ($data['attrType'] === 'select') {
            $options = collect($this->attrOptions)
                ->map(fn (array $o) => ['label' => trim((string) ($o['label'] ?? '')), 'color' => $o['color'] ?? CategoryAttribute::OPTION_COLORS[0]])
                ->filter(fn (array $o) => $o['label'] !== '')
                ->unique('label')
                ->values()
                ->all();

            if ($options === []) {
                $this->addError('attrOptions', 'Mindestens eine Option angeben.');

                return;
            }
        }

        $payload = [
            'name' => trim($data['attrName']),
            'type' => $data['attrType'],
            'options' => $options,
            'unit' => $data['attrType'] === 'number' && trim((string) $data['attrUnit']) !== '' ? trim($data['attrUnit']) : null,
        ];

        if ($this->editingAttributeId !== null) {
            $category->customAttributes()->whereKey($this->editingAttributeId)->update($payload);
        } else {
            $category->customAttributes()->create($payload + ['sort_order' => $category->customAttributes()->count()]);
        }

        $this->startAddAttribute();
    }

    public function deleteAttribute(int $id): void
    {
        $category = auth()->user()->eventCategories()->findOrFail($this->managingAttributesCategoryId);
        $category->customAttributes()->whereKey($id)->delete();

        if ($this->editingAttributeId === $id) {
            $this->startAddAttribute();
        }
    }

    // ── Category task links (Pomodoro focus-session suggestions) ────────

    /** Opens the link sheet for one category — closes/replaces whatever was open before. */
    public function manageCategoryLink(int $id): void
    {
        $category = auth()->user()->eventCategories()->findOrFail($id);

        $this->linkingCategoryId = $category->id;
        $this->linkTextDraft = $category->task_source === 'text' ? (string) $category->linked_text : '';
        $this->linkTaskSearch = '';
    }

    public function closeCategoryLink(): void
    {
        $this->linkingCategoryId = null;
    }

    /** The category currently open in the link sheet, with every possible link target eager-loaded. */
    #[Computed]
    public function linkingCategory(): ?EventCategory
    {
        if ($this->linkingCategoryId === null) {
            return null;
        }

        return auth()->user()->eventCategories()
            ->with(['linkedProject', 'linkedGroup', 'linkedAgendaEntry', 'pinnedTasks'])
            ->find($this->linkingCategoryId);
    }

    #[Computed]
    public function linkableProjects(): Collection
    {
        return auth()->user()->projects()->ordered()->get();
    }

    #[Computed]
    public function linkableGroups(): Collection
    {
        return auth()->user()->taskGroups()->ordered()->get();
    }

    /** Open homework/exam entries this user can see — for picking a single Agenda link. */
    #[Computed]
    public function linkableAgendaEntries(): Collection
    {
        $user = auth()->user();

        return AgendaEntry::visibleTo($user)->openFor($user)->ordered()->get();
    }

    /**
     * Candidates for "bestimmte Aufgaben": with no search typed, tasks due within the
     * next 2 days or with a Wunschtermin today — the moment the picker opens, before
     * the user has to think of a title. Typing a search searches every active board
     * task instead, so something outside that window can still be found and pinned.
     */
    #[Computed]
    public function linkTaskCandidates(): Collection
    {
        $user = auth()->user();
        $query = Task::forUser($user)->active()->onBoard();

        if ($this->linkTaskSearch !== '') {
            return $query->where('title', 'like', '%'.$this->linkTaskSearch.'%')->boardOrdered()->get();
        }

        $today = $user->localToday()->toDateString();
        $horizon = $user->localToday()->addDays(2)->toDateString();

        // whereDate(), not whereBetween()/where() on the raw column: 'deadline'/'due_date' are
        // plain 'date' casts, which store full datetime precision under the hood (see CLAUDE.md
        // "An exact where() against a 'date'-cast column can silently match nothing") — whereDate()
        // wraps both sides in the grammar's own DATE() extraction so the comparison is exact
        // regardless of stored precision, both for the range and for the due-date equality check.
        return $query->where(function (Builder $q) use ($today, $horizon) {
            $q->where(fn (Builder $d) => $d->whereDate('deadline', '>=', $today)->whereDate('deadline', '<=', $horizon))
                ->orWhereDate('due_date', $today);
        })->boardOrdered()->get();
    }

    public function clearCategoryLink(int $id): void
    {
        auth()->user()->eventCategories()->findOrFail($id)->clearTaskLink();
        unset($this->linkingCategory);
    }

    public function linkCategoryToProject(int $id, int $projectId): void
    {
        $category = auth()->user()->eventCategories()->findOrFail($id);
        $project = auth()->user()->projects()->findOrFail($projectId);

        $category->clearTaskLink();
        $category->update(['task_source' => 'project', 'linked_project_id' => $project->id]);
        unset($this->linkingCategory);
    }

    public function linkCategoryToGroup(int $id, int $groupId): void
    {
        $category = auth()->user()->eventCategories()->findOrFail($id);
        $group = auth()->user()->taskGroups()->findOrFail($groupId);

        $category->clearTaskLink();
        $category->update(['task_source' => 'group', 'linked_group_id' => $group->id]);
        unset($this->linkingCategory);
    }

    public function linkCategoryToAgendaEntry(int $id, int $agendaEntryId): void
    {
        $category = auth()->user()->eventCategories()->findOrFail($id);
        $entry = AgendaEntry::visibleTo(auth()->user())->findOrFail($agendaEntryId);

        $category->clearTaskLink();
        $category->update(['task_source' => 'agenda_entry', 'linked_agenda_entry_id' => $entry->id]);
        unset($this->linkingCategory);
    }

    public function linkCategoryToAgendaGeneric(int $id): void
    {
        $category = auth()->user()->eventCategories()->findOrFail($id);

        $category->clearTaskLink();
        $category->update(['task_source' => 'agenda_generic']);
        unset($this->linkingCategory);
    }

    public function saveCategoryLinkText(int $id): void
    {
        $data = $this->validate(['linkTextDraft' => ['required', 'string', 'max:255']]);

        $category = auth()->user()->eventCategories()->findOrFail($id);
        $category->clearTaskLink();
        $category->update(['task_source' => 'text', 'linked_text' => trim($data['linkTextDraft'])]);
        unset($this->linkingCategory);
    }

    /** Switches into "bestimmte Aufgaben" mode with nothing pinned yet — the task picker appears once this is set. */
    public function setCategoryTasksMode(int $id): void
    {
        $category = auth()->user()->eventCategories()->findOrFail($id);

        if ($category->task_source !== 'tasks') {
            $category->clearTaskLink();
            $category->update(['task_source' => 'tasks']);
            unset($this->linkingCategory);
        }
    }

    /** Adds or removes one task from a category's pinned set — only meaningful once setCategoryTasksMode() has run. */
    public function togglePinnedTask(int $id, int $taskId): void
    {
        $category = auth()->user()->eventCategories()->findOrFail($id);

        if ($category->task_source !== 'tasks') {
            return;
        }

        $task = auth()->user()->tasks()->findOrFail($taskId);

        if ($category->pinnedTasks()->whereKey($task->id)->exists()) {
            $category->pinnedTasks()->detach($task->id);
        } else {
            $nextOrder = (int) ($category->pinnedTasks()->max('category_task_links.sort_order') ?? -1);
            $category->pinnedTasks()->attach($task->id, ['sort_order' => $nextOrder + 1]);
        }

        unset($this->linkingCategory);
    }

    // ── Shortcuts & API tokens ──────────────────────────────────────────

    public string $newTokenName = '';

    /** The plaintext token, shown exactly once right after creation — never stored, never shown again. */
    public ?string $createdToken = null;

    #[Computed]
    public function apiTokens(): Collection
    {
        return auth()->user()->tokens()->latest()->get();
    }

    public function createApiToken(): void
    {
        $this->newTokenName = trim($this->newTokenName);

        $data = $this->validate([
            'newTokenName' => ['required', 'string', 'max:255'],
        ]);

        $token = auth()->user()->createToken($data['newTokenName']);

        $this->createdToken = $token->plainTextToken;
        $this->newTokenName = '';
        unset($this->apiTokens);
    }

    public function dismissCreatedToken(): void
    {
        $this->createdToken = null;
    }

    public function revokeApiToken(int $id): void
    {
        auth()->user()->tokens()->whereKey($id)->delete();
        unset($this->apiTokens);
    }

    public function render()
    {
        return view('livewire.settings');
    }
}
