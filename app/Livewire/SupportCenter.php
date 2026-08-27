<?php

namespace App\Livewire;

use App\Models\SupportRequest;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Submit feedback/support requests and see the status of your own past
 * ones — mirrors Agenda's single-page "form + list" shape. See CLAUDE.md,
 * "Hilfe-Center & Support".
 */
#[Layout('layouts.app')]
class SupportCenter extends Component
{
    public string $formType = SupportRequest::DEFAULT_TYPE;

    public string $formSubject = '';

    public string $formMessage = '';

    public bool $justSubmitted = false;

    /** @return Collection<int, SupportRequest> */
    #[Computed]
    public function myRequests(): Collection
    {
        return auth()->user()->supportRequests()->newestFirst()->get();
    }

    public function submit(): void
    {
        $this->formSubject = trim($this->formSubject);
        $this->formMessage = trim($this->formMessage);

        $data = $this->validate([
            'formType' => ['required', Rule::in(array_keys(SupportRequest::TYPES))],
            'formSubject' => ['required', 'string', 'max:255'],
            'formMessage' => ['required', 'string', 'max:5000'],
        ]);

        auth()->user()->supportRequests()->create([
            'type' => $data['formType'],
            'subject' => $data['formSubject'],
            'message' => $data['formMessage'],
            'status' => SupportRequest::DEFAULT_STATUS,
        ]);

        $this->reset(['formSubject', 'formMessage']);
        $this->formType = SupportRequest::DEFAULT_TYPE;
        $this->justSubmitted = true;
        unset($this->myRequests);
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'formSubject.required' => 'Ohne Betreff geht es nicht.',
            'formMessage.required' => 'Beschreib kurz, worum es geht.',
        ];
    }

    public function render()
    {
        return view('livewire.support-center');
    }
}
