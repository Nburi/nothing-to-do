<?php

namespace App\Livewire\Concerns;

use App\Services\AppModules;
use Livewire\Attributes\Computed;

/**
 * Module visibility + default landing page — shared between Settings' own
 * "Module"/"Startseite" cards and the Onboarding tutorial's interactive step,
 * so the self-healing "hiding the current default page resets it to the
 * board" rule lives in exactly one place rather than drifting between two
 * copies. See App\Services\AppModules for the underlying catalog.
 */
trait ManagesModuleSettings
{
    public string $defaultPage = 'app';

    public function mountManagesModuleSettings(): void
    {
        $this->defaultPage = auth()->user()->default_page ?? 'app';
    }

    /**
     * @return list<array{key: string, label: string, description: string, hidden: bool}>
     */
    #[Computed]
    public function moduleRows(): array
    {
        return AppModules::rowsFor(auth()->user());
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    #[Computed]
    public function landingPageOptions(): array
    {
        return AppModules::landingPageOptions(auth()->user());
    }

    /**
     * Hides/reveals one module. If the module being hidden is also the
     * user's current default landing page, that choice is reset to the
     * board in the same write — a hidden page must never stay selected as
     * "where the app opens" (self-healing, mirroring
     * User::defaultLandingRouteName()'s own fallback for the read side).
     */
    public function toggleModule(string $key): void
    {
        if (! array_key_exists($key, AppModules::CATALOG)) {
            return;
        }

        $user = auth()->user();
        $hidden = AppModules::hiddenKeys($user);
        $nowHidden = ! in_array($key, $hidden, true);

        $hidden = $nowHidden
            ? [...$hidden, $key]
            : array_values(array_diff($hidden, [$key]));

        $updates = ['hidden_modules' => $hidden];

        if ($nowHidden && $this->defaultPage === $key) {
            $updates['default_page'] = 'app';
            $this->defaultPage = 'app';
        }

        $user->update($updates);
        unset($this->moduleRows, $this->landingPageOptions);
    }

    /** Which page opens on '/' and right after login — only settable to something currently visible. */
    public function setDefaultPage(string $key): void
    {
        $user = auth()->user();

        if (! AppModules::isValidLandingPage($user, $key)) {
            return;
        }

        $this->defaultPage = $key;
        $user->update(['default_page' => $key]);
    }
}
