<?php

namespace App\Livewire;

use App\Models\PushSubscription;
use App\Models\ScheduleEvent;
use App\Services\PushNotifier;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Settings extends Component
{
    public string $resetTime = '01:00';

    // Pomodoro rhythm
    public int $pWork = 25;

    public int $pShortBreak = 5;

    public int $pLongBreak = 15;

    public int $pLongEvery = 4;

    public bool $pAutostart = false;

    // Timezone
    public float $timezoneOffset = 0;

    public bool $timezoneAutoDst = false;

    // Add-category form
    public string $newCategoryName = '';

    public string $newCategoryColor = 'contour';

    public function mount(): void
    {
        $user = auth()->user();

        $this->resetTime = $user->task_reset_time ?? '01:00';
        $this->pWork = $user->pomodoro_work ?? 25;
        $this->pShortBreak = $user->pomodoro_short_break ?? 5;
        $this->pLongBreak = $user->pomodoro_long_break ?? 15;
        $this->pLongEvery = $user->pomodoro_long_every ?? 4;
        $this->pAutostart = $user->pomodoro_autostart ?? false;
        $this->timezoneOffset = (float) ($user->timezone_offset ?? 0);
        $this->timezoneAutoDst = $user->timezone_auto_dst ?? false;
    }

    public function save(): void
    {
        $data = $this->validate([
            'resetTime' => ['required', 'date_format:H:i'],
        ]);

        auth()->user()->update(['task_reset_time' => $data['resetTime']]);

        $this->dispatch('saved');
    }

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
            'pomodoro_autostart' => $this->pAutostart,
        ]);

        $this->dispatch('schedule-saved');
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

    public function saveTimezone(): void
    {
        $data = $this->validate([
            'timezoneOffset' => ['required', 'numeric', 'between:-12,14'],
            'timezoneAutoDst' => ['boolean'],
        ]);

        auth()->user()->update([
            'timezone_offset' => $data['timezoneOffset'],
            'timezone_auto_dst' => $data['timezoneAutoDst'],
        ]);

        $this->dispatch('timezone-saved');
    }

    /** The user's categories, for the settings list. */
    #[Computed]
    public function categories(): Collection
    {
        return auth()->user()->eventCategories()->ordered()->get();
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
