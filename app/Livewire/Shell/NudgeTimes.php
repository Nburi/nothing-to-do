<?php

namespace App\Livewire\Shell;

use App\Livewire\Concerns\ManagesPushSubscription;
use App\Services\Planning\EveningNudge;
use App\Support\UserClock;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The "Abschalten am Abend" card in the new settings (step 3.3, R2): one optional time per
 * weekday. From that time the board offers the reset, and one push goes out (EveningNudge).
 * The card also shows whether this device gets notifications and lets the user allow them,
 * with the same subscription code as the old settings (ManagesPushSubscription).
 */
class NudgeTimes extends Component
{
    use ManagesPushSubscription;

    /** @var array<int, string> ISO weekday => "HH:MM" or "" */
    public array $times = [];

    /** "saved" after a save, "error" when it failed, null otherwise. */
    public ?string $status = null;

    public ?string $savedNote = null;

    public function mount(): void
    {
        $this->fill(['times' => $this->stored()]);
    }

    /** "Zeit setzen": the last time used above, else 18:00, so a week is three taps. */
    public function addTime(int $day): void
    {
        if (! in_array($day, EveningNudge::WEEKDAYS, true)) {
            return;
        }

        $previous = collect(EveningNudge::WEEKDAYS)
            ->filter(fn (int $d) => $d < $day && ($this->times[$d] ?? '') !== '')
            ->map(fn (int $d) => $this->times[$d])
            ->last();

        $this->times[$day] = $previous ?? '18:00';
        $this->status = null;
    }

    public function clearTime(int $day): void
    {
        if (in_array($day, EveningNudge::WEEKDAYS, true)) {
            $this->times[$day] = '';
            $this->resetErrorBag("times.{$day}");
            $this->status = null;
        }
    }

    /** "Mo–Fr wie Montag". */
    public function copyWeekdays(): void
    {
        $monday = $this->times[1] ?? '';

        if ($monday === '') {
            return;
        }

        foreach ([2, 3, 4, 5] as $day) {
            $this->times[$day] = $monday;
            $this->resetErrorBag("times.{$day}");
        }
        $this->status = null;
    }

    public function updatedTimes(): void
    {
        $this->status = null;
    }

    public function save(): void
    {
        $this->status = null;
        $this->times = array_map(fn ($t) => trim((string) $t), $this->times + array_fill_keys(EveningNudge::WEEKDAYS, ''));

        $rules = [];
        $messages = [];
        foreach (EveningNudge::WEEKDAYS as $day) {
            $rules["times.{$day}"] = ['nullable', 'string', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'];
            $messages["times.{$day}.regex"] = __('shell.nudge.errors.time');
            $messages["times.{$day}.string"] = __('shell.nudge.errors.time');
        }

        $this->validate($rules, $messages);

        try {
            $user = Auth::user();
            app(EveningNudge::class)->save($user, $this->times);
        } catch (\Throwable $e) {
            report($e);
            Log::warning('Saving nudge times failed', ['user_id' => Auth::id()]);
            $this->status = 'error';

            return;
        }

        $this->times = $this->stored();
        $today = app(EveningNudge::class)->timeFor($user, UserClock::now($user, CarbonImmutable::now()));
        $this->savedNote = $today !== null ? __('shell.nudge.saved_tonight', ['time' => $today]) : __('shell.nudge.saved_quiet');
        $this->status = 'saved';
        unset($this->configured);
    }

    #[Computed]
    public function configured(): bool
    {
        return EveningNudge::configured(Auth::user()->fresh());
    }

    /** @return array<int, string> */
    private function stored(): array
    {
        $saved = EveningNudge::times(Auth::user()->fresh());

        return array_map(fn (int $day) => $saved[$day] ?? '', array_combine(EveningNudge::WEEKDAYS, EveningNudge::WEEKDAYS));
    }

    public function render(): View
    {
        $user = Auth::user();
        $now = CarbonImmutable::now();
        $monday = $now->startOfWeek();

        $days = [];
        foreach (EveningNudge::WEEKDAYS as $day) {
            $date = $monday->addDays($day - 1)->locale(app()->getLocale());
            $days[$day] = ['short' => $date->isoFormat('dd'), 'long' => $date->isoFormat('dddd')];
        }

        $hasAny = collect($this->times)->contains(fn ($t) => $t !== '');

        return view('livewire.shell.nudge-times', [
            'days' => $days,
            'hasAny' => $hasAny,
            'zone' => UserClock::timezone($user, $now),
            'devices' => $user->pushSubscriptions()->count(),
        ]);
    }
}
