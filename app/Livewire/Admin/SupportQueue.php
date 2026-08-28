<?php

namespace App\Livewire\Admin;

use App\Models\SupportRequest;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin-only triage queue for every feedback/support request, across every
 * user. Gated in mount(), not by route middleware — same convention as
 * AnnouncementEditor. See CLAUDE.md, "Hilfe-Center & Support".
 */
#[Layout('layouts.app')]
class SupportQueue extends Component
{
    /** A key into SupportRequest::STATUSES, or null for "alle". */
    public ?string $statusFilter = null;

    /** Which request's response textarea is currently open, if any. */
    public ?int $respondingId = null;

    public string $responseDraft = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->is_admin, 403);
    }

    /** @return Collection<int, SupportRequest> */
    #[Computed]
    public function requests(): Collection
    {
        return SupportRequest::query()
            ->with('user')
            ->ofStatus($this->statusFilter)
            ->newestFirst()
            ->get();
    }

    /** @return array<string, int> */
    #[Computed]
    public function statusCounts(): array
    {
        $counts = SupportRequest::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $statusKeys = array_keys(SupportRequest::STATUSES);

        return [
            'all' => (int) $counts->sum(),
            ...array_combine($statusKeys, array_map(fn ($key) => (int) ($counts[$key] ?? 0), $statusKeys)),
        ];
    }

    public function setStatusFilter(?string $status): void
    {
        $this->statusFilter = $status;
    }

    public function setStatus(int $id, string $status): void
    {
        if (! array_key_exists($status, SupportRequest::STATUSES)) {
            return;
        }

        SupportRequest::findOrFail($id)->update(['status' => $status]);
        unset($this->requests, $this->statusCounts);
    }

    public function startResponding(int $id): void
    {
        $request = SupportRequest::findOrFail($id);

        $this->respondingId = $id;
        $this->responseDraft = (string) ($request->response ?? '');
    }

    public function cancelResponding(): void
    {
        $this->respondingId = null;
        $this->responseDraft = '';
    }

    public function saveResponse(): void
    {
        if ($this->respondingId === null) {
            return;
        }

        $draft = trim($this->responseDraft);

        SupportRequest::findOrFail($this->respondingId)->update([
            'response' => $draft !== '' ? $draft : null,
            'responded_by' => $draft !== '' ? auth()->id() : null,
        ]);

        $this->cancelResponding();
        unset($this->requests);
    }

    public function render()
    {
        return view('livewire.admin.support-queue');
    }
}
