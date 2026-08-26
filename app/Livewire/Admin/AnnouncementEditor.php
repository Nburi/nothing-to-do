<?php

namespace App\Livewire\Admin;

use App\Models\FeatureAnnouncement;
use App\Services\AppModules;
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

    /** A key into AppModules::CATALOG, or '' for "kein bestimmter Bereich". */
    public string $formRelatedModule = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->is_admin, 403);
    }

    /** Every announcement, draft and published alike — an admin needs to see both. */
    #[Computed]
    public function announcements(): Collection
    {
        return FeatureAnnouncement::query()->newestFirst()->get();
    }

    #[Computed]
    public function moduleOptions(): array
    {
        return AppModules::CATALOG;
    }

    #[Computed]
    public function typeOptions(): array
    {
        return FeatureAnnouncement::TYPES;
    }

    public function openCreateForm(): void
    {
        $this->editingId = null;
        $this->formTitle = '';
        $this->formDescription = '';
        $this->formType = FeatureAnnouncement::DEFAULT_TYPE;
        $this->formRelatedModule = '';
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

        $data = $this->validate([
            'formTitle' => ['required', 'string', 'max:255'],
            'formDescription' => ['required', 'string', 'max:500'],
            'formType' => ['required', Rule::in(array_keys(FeatureAnnouncement::TYPES))],
            'formRelatedModule' => ['nullable', Rule::in(array_keys(AppModules::CATALOG))],
        ]);

        $attributes = [
            'title' => $data['formTitle'],
            'description' => $data['formDescription'],
            'type' => $data['formType'],
            'related_module' => $data['formRelatedModule'] !== '' ? $data['formRelatedModule'] : null,
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
