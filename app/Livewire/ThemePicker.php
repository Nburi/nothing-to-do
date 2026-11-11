<?php

namespace App\Livewire;

use App\Support\Theme;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * The "Aussehen" card (step 2.1): seven themes and System / Hell / Dunkel, saved per user.
 *
 * After a save it dispatches `theme-applied` with the new stylesheet; app.js swaps the
 * <style id="theme-vars"> in the head, so the whole page repaints without a reload.
 * Embedded in the old Settings page for now and in the new shell's settings from step 2.2.
 */
class ThemePicker extends Component
{
    public string $theme = '';

    public string $mode = 'system';

    public function mount(): void
    {
        $user = Auth::user();
        $this->theme = Theme::for($user);
        $this->mode = Theme::modeFor($user);
    }

    public function selectTheme(string $id): void
    {
        if (! Theme::exists($id)) {
            $this->addError('theme', __('themes.unknown_theme'));

            return;
        }

        $this->resetErrorBag();
        $this->theme = $id;
        $this->persist();
    }

    public function selectMode(string $mode): void
    {
        if (! in_array($mode, Theme::MODES, true)) {
            $this->addError('mode', __('themes.unknown_mode'));

            return;
        }

        $this->resetErrorBag();
        $this->mode = $mode;
        $this->persist();
    }

    private function persist(): void
    {
        $user = Auth::user();
        $user->forceFill(['theme' => $this->theme, 'color_mode' => $this->mode])->save();

        $this->dispatch(
            'theme-applied',
            css: Theme::css($this->theme, $this->mode),
            mode: $this->mode,
            chrome: [
                'light' => Theme::chromeColor($this->theme, $this->mode === 'dark' ? 'dark' : 'light'),
                'dark' => Theme::chromeColor($this->theme, $this->mode === 'light' ? 'light' : 'dark'),
            ],
        );
    }

    public function render(): View
    {
        $themes = [];

        foreach (Theme::ids() as $id) {
            $definition = Theme::definition($id);
            $themes[] = [
                'id' => $id,
                'name' => $definition['name'],
                'font' => $definition['fonts']['display'],
                'weight' => $definition['display_weight'],
                'description' => __("themes.descriptions.{$id}"),
                'light' => Theme::inlineStyle($id, 'light'),
                'dark' => Theme::inlineStyle($id, 'dark'),
            ];
        }

        return view('livewire.theme-picker', ['themes' => $themes]);
    }
}
