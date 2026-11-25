<?php

namespace App\Livewire\Shell;

use App\Support\Locales;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * The "Sprache" card of the settings (step 5.1): German or English, saved per user.
 *
 * The page reloads after a save (a real redirect, not a Livewire navigation), so the whole page,
 * the layout and every other card come back in the new language at once.
 */
class LanguagePicker extends Component
{
    public string $locale = Locales::DEFAULT;

    public function mount(): void
    {
        $this->locale = Locales::for(Auth::user());
    }

    public function select(string $locale): void
    {
        if (! Locales::supports($locale)) {
            $this->addError('locale', __('onboarding.language.unknown'));

            return;
        }

        $this->resetErrorBag();
        Auth::user()->forceFill(['locale' => $locale])->save();

        $this->redirectRoute('shell.settings');
    }

    public function render(): View
    {
        return view('livewire.shell.language-picker', ['options' => Locales::SUPPORTED]);
    }
}
