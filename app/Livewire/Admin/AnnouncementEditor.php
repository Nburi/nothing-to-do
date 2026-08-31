<?php

namespace App\Livewire\Admin;

use App\Models\FeatureAnnouncement;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin-only editor for App\Models\FeatureAnnouncement — the authoring side of
 * the "here's what's new" toast every user sees in
 * App\Livewire\FeatureAnnouncementToast. Gated in mount(), not by route
 * middleware: every other authorization boundary in this app already lives at
 * the component/query level (userTask(), visibleEntry(), …), not in a
 * middleware layer, so this follows the same convention. See CLAUDE.md,
 * Feature-Ankündigungen.
 */
#[Layout('layouts.app')]
class AnnouncementEditor extends Component
{
    /** Null while creating; the announcement's id while editing. */
    public ?int $editingId = null;

    public string $formTitle = '';

    public string $formDescription = '';

    /** A key into FeatureAnnouncement::TYPES. */
    public string $formType = FeatureAnnouncement::DEFAULT_TYPE;

    /** A key into FeatureAnnouncement::linkableModules(), or '' for "kein bestimmter Bereich". */
    public string $formRelatedModule = '';

    /**
     * Whether this announcement should only reach people who have actually
     * visited formRelatedModule's page (see FeatureAnnouncement::isModuleInUseBy()),
     * instead of everyone. Only meaningful — and only ever rendered — when a
     * scopable module is chosen; save() forces this back to false otherwise,
     * regardless of what a stale form field might still hold.
     */
    public bool $formOnlyForModuleUsers = false;

    /** 'none' | 'module' | 'external' — which of the two link fields below applies, mutually exclusive. */
    public string $formLinkType = 'none';

    public string $formExternalUrl = '';

    public string $formExternalLinkLabel = '';

    /** A CSS selector to scroll to and flash after following an internal link — see FeatureAnnouncement::linkHref(). */
    public string $formHighlightSelector = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->is_admin, 403);
    }

    /**
     * Every announcement, draft and published alike — an admin needs to see
     * both. withCount avoids an N+1 for each row's "N gesehen" figure.
     */
    #[Computed]
    public function announcements(): Collection
    {
        return FeatureAnnouncement::query()->withCount('dismissedBy')->newestFirst()->get();
    }

    #[Computed]
    public function moduleOptions(): array
    {
        return FeatureAnnouncement::linkableModules();
    }

    /**
     * Real reach numbers for the currently-selected module — null unless a
     * scopable module is actually chosen (isScopableModule() gates whether
     * the "only for module users" toggle even renders, see the view). Backs
     * this feature's one signature moment: the count is live, not something
     * you only find out after publishing.
     *
     * @return array{total: int, inUse: int}|null
     */
    #[Computed]
    public function moduleUsageEstimate(): ?array
    {
        if ($this->formLinkType !== 'module' || $this->formRelatedModule === '') {
            return null;
        }

        if (! FeatureAnnouncement::isScopableModule($this->formRelatedModule)) {
            return null;
        }

        return FeatureAnnouncement::moduleReachCounts($this->formRelatedModule);
    }

    #[Computed]
    public function typeOptions(): array
    {
        return FeatureAnnouncement::TYPES;
    }

    /**
     * An unsaved FeatureAnnouncement built from the current form fields, fed
     * into partials/announcement-toast-card.blade.php so the form's "Vorschau"
     * renders pixel-identical to what a user would actually see — the same
     * card the real toast renders, never a second copy of that markup. Empty
     * fields get placeholder text rather than showing a blank card.
     */
    #[Computed]
    public function previewAnnouncement(): FeatureAnnouncement
    {
        return new FeatureAnnouncement([
            'title' => $this->formTitle,
            'description' => $this->formDescription,
            'type' => $this->formType,
            'related_module' => $this->formLinkType === 'module' && $this->formRelatedModule !== '' ? $this->formRelatedModule : null,
            'only_for_module_users' => $this->formLinkType === 'module' && $this->formOnlyForModuleUsers,
            'external_url' => $this->formLinkType === 'external' && $this->formExternalUrl !== '' ? $this->formExternalUrl : null,
            'external_link_label' => $this->formLinkType === 'external' && $this->formExternalLinkLabel !== '' ? $this->formExternalLinkLabel : null,
            'highlight_selector' => $this->formLinkType === 'module' && $this->formHighlightSelector !== '' ? $this->formHighlightSelector : null,
        ]);
    }

    public function openCreateForm(): void
    {
        $this->editingId = null;
        $this->formTitle = '';
        $this->formDescription = '';
        $this->formType = FeatureAnnouncement::DEFAULT_TYPE;
        $this->formRelatedModule = '';
        $this->formOnlyForModuleUsers = false;
        $this->formLinkType = 'none';
        $this->formExternalUrl = '';
        $this->formExternalLinkLabel = '';
        $this->formHighlightSelector = '';
        $this->resetValidation();
    }

    public function startEdit(int $id): void
    {
        $announcement = FeatureAnnouncement::findOrFail($id);

        $this->editingId = $announcement->id;
        $this->formTitle = $announcement->title;
        $this->formDescription = $announcement->description;
        $this->formType = $announcement->type;
        $this->formRelatedModule = (string) ($announcement->related_module ?? '');
        $this->formOnlyForModuleUsers = $announcement->only_for_module_users;
        $this->formLinkType = match (true) {
            $announcement->related_module !== null => 'module',
            $announcement->external_url !== null => 'external',
            default => 'none',
        };
        $this->formExternalUrl = (string) ($announcement->external_url ?? '');
        $this->formExternalLinkLabel = (string) ($announcement->external_link_label ?? '');
        $this->formHighlightSelector = (string) ($announcement->highlight_selector ?? '');
        $this->resetValidation();
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
    }

    public function save(): void
    {
        $this->formTitle = trim($this->formTitle);
        $this->formDescription = trim($this->formDescription);
        $this->formExternalUrl = trim($this->formExternalUrl);
        $this->formExternalLinkLabel = trim($this->formExternalLinkLabel);
        $this->formHighlightSelector = trim($this->formHighlightSelector);

        $data = $this->validate([
            'formTitle' => ['required', 'string', 'max:255'],
            'formDescription' => ['required', 'string', 'max:500'],
            'formType' => ['required', Rule::in(array_keys(FeatureAnnouncement::TYPES))],
            'formLinkType' => ['required', Rule::in(['none', 'module', 'external'])],
            'formRelatedModule' => [Rule::requiredIf($this->formLinkType === 'module'), 'nullable', Rule::in(array_keys(FeatureAnnouncement::linkableModules()))],
            'formExternalUrl' => [Rule::requiredIf($this->formLinkType === 'external'), 'nullable', 'url', 'max:2048'],
            'formExternalLinkLabel' => ['nullable', 'string', 'max:100'],
            'formHighlightSelector' => ['nullable', 'string', 'max:255'],
        ]);

        // The two link kinds are mutually exclusive — whichever isn't the
        // chosen formLinkType is always cleared, regardless of what a stale
        // form field might still hold from switching chips back and forth.
        $attributes = [
            'title' => $data['formTitle'],
            'description' => $data['formDescription'],
            'type' => $data['formType'],
            'related_module' => $this->formLinkType === 'module' ? $data['formRelatedModule'] : null,
            'only_for_module_users' => $this->formLinkType === 'module'
                && FeatureAnnouncement::isScopableModule($data['formRelatedModule'] ?? '')
                && $this->formOnlyForModuleUsers,
            'external_url' => $this->formLinkType === 'external' ? $data['formExternalUrl'] : null,
            'external_link_label' => $this->formLinkType === 'external' && $data['formExternalLinkLabel'] !== ''
                ? $data['formExternalLinkLabel']
                : null,
            'highlight_selector' => $this->formLinkType === 'module' && $data['formHighlightSelector'] !== ''
                ? $data['formHighlightSelector']
                : null,
        ];

        if ($this->editingId !== null) {
            FeatureAnnouncement::findOrFail($this->editingId)->update($attributes);
        } else {
            auth()->user()->createdAnnouncements()->create($attributes);
        }

        $this->openCreateForm();
        unset($this->announcements);
    }

    /**
     * Publishing stamps published_at only the first time — unpublishing and
     * republishing later must not move it, since it marks when the
     * announcement was actually introduced, not the current toggle state.
     */
    public function togglePublish(int $id): void
    {
        $announcement = FeatureAnnouncement::findOrFail($id);

        $announcement->update([
            'is_published' => ! $announcement->is_published,
            'published_at' => $announcement->published_at ?? now(),
        ]);

        unset($this->announcements);
    }

    public function deleteAnnouncement(int $id): void
    {
        FeatureAnnouncement::findOrFail($id)->delete();

        if ($this->editingId === $id) {
            $this->cancelEdit();
        }

        unset($this->announcements);
    }

    public function render()
    {
        return view('livewire.admin.announcement-editor');
    }
}
