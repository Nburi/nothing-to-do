<?php

namespace App\Livewire;

use App\Livewire\Concerns\ManagesFamilySpaces;
use App\Models\FamilySpace;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The landing page an invite link points at (/app/family/join/{code}).
 * Mirrors JoinAgendaSpace exactly, including the "join is a button press,
 * never a side effect of the GET" rule — a link pasted into a family chat
 * gets fetched by link previewers long before a human clicks it.
 */
#[Layout('layouts.app')]
class JoinFamilySpace extends Component
{
    use ManagesFamilySpaces;

    public string $code = '';

    public ?FamilySpace $space = null;

    public bool $alreadyMember = false;

    public function mount(string $code): void
    {
        $this->code = $code;
        $this->space = FamilySpace::findByInviteCode($code);

        $this->alreadyMember = $this->space !== null
            && $this->space->hasMember(auth()->user());
    }

    public function join()
    {
        if ($this->space === null) {
            return null;
        }

        $this->joinFamilyByCode($this->space->invite_code);

        session()->flash('family-joined', $this->space->name);

        return $this->redirectRoute('family', navigate: true);
    }

    public function render()
    {
        return view('livewire.join-family-space', [
            'memberCount' => $this->space?->members()->count() ?? 0,
        ]);
    }
}
