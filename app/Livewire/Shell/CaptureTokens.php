<?php

namespace App\Livewire\Shell;

use App\Support\NarrowAbility;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Create and revoke the narrow capture token (step 2.3, risk C10): before this only
 * `php artisan app:capture-token` could make one. A capture token can call POST /api/capture
 * and nothing else (RejectNarrowTokens). The plain token is shown once and never stored.
 * Revoking asks once, inline: it cannot be undone, so it is the one confirm in the app.
 */
class CaptureTokens extends Component
{
    /** At most this many capture tokens per user. */
    public const MAX = 10;

    public string $name = 'iPhone';

    /** The token just created, shown once. */
    public ?string $plain = null;

    public ?string $plainName = null;

    public ?int $confirming = null;

    public ?string $notice = null;

    /** @return Collection<int, PersonalAccessToken> */
    #[Computed]
    public function tokens(): Collection
    {
        return Auth::user()->tokens()
            ->orderByDesc('created_at')
            ->get()
            ->filter(fn (PersonalAccessToken $t) => $t->abilities === [NarrowAbility::CAPTURE])
            ->values();
    }

    public function create(): void
    {
        $this->notice = null;
        $this->name = trim($this->name);

        $this->validate(
            ['name' => ['required', 'string', 'max:40']],
            ['name.required' => __('capture.tokens.errors.name_required'), 'name.max' => __('capture.tokens.errors.name_max')],
        );

        if ($this->tokens->count() >= self::MAX) {
            $this->addError('name', __('capture.tokens.errors.too_many', ['max' => self::MAX]));

            return;
        }

        $this->plain = Auth::user()->createToken($this->name, [NarrowAbility::CAPTURE])->plainTextToken;
        $this->plainName = $this->name;
        $this->name = 'iPhone';
        unset($this->tokens);
    }

    /** "Fertig, Token ist kopiert": hide the plain token for good. */
    public function dismiss(): void
    {
        $this->plain = null;
        $this->plainName = null;
    }

    public function askRevoke(int $id): void
    {
        $this->confirming = $id;
        $this->notice = null;
    }

    public function keep(): void
    {
        $this->confirming = null;
    }

    public function revoke(int $id): void
    {
        $token = $this->tokens->firstWhere('id', $id);
        $this->confirming = null;

        if ($token === null) {
            $this->notice = __('capture.tokens.gone');

            return;
        }

        $token->delete();
        $this->notice = __('capture.tokens.revoked', ['name' => $token->name]);
        unset($this->tokens);
    }

    public function render(): View
    {
        return view('livewire.shell.capture-tokens', ['captureUrl' => url('/api/capture')]);
    }
}
