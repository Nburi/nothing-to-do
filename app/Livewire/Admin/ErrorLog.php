<?php

namespace App\Livewire\Admin;

use App\Models\ErrorOccurrence;
use App\Services\ErrorStats;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin-only "Fehler-Statistiken" — every rendered HTML error page
 * (404/403/419/500/...), aggregated. Gated in mount(), not by route
 * middleware — same convention as AnnouncementEditor/HelpEditor/
 * SupportQueue. See CLAUDE.md, "Fehler-Statistiken", and App\Services\
 * ErrorStats for how these rows get written in the first place.
 */
#[Layout('layouts.app')]
class ErrorLog extends Component
{
    /** A status code to filter the recent list by, or null for "alle". */
    public ?int $statusFilter = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->is_admin, 403);
    }

    /** @return array<int, int> status code => count, most frequent first. */
    #[Computed]
    public function statusCounts(): array
    {
        return ErrorStats::countsByStatus();
    }

    /** @return array<string, int> ISO date => count, for the last 14 days. */
    #[Computed]
    public function dayCounts(): array
    {
        return ErrorStats::countsByDay(14);
    }

    #[Computed]
    public function topPaths(): \Illuminate\Support\Collection
    {
        return ErrorStats::topPaths(10);
    }

    /** @return Collection<int, ErrorOccurrence> */
    #[Computed]
    public function occurrences(): Collection
    {
        return ErrorOccurrence::query()
            ->with('user')
            ->ofStatus($this->statusFilter)
            ->newestFirst()
            ->limit(50)
            ->get();
    }

    public function setStatusFilter(?int $status): void
    {
        $this->statusFilter = $status;
    }

    public function render()
    {
        return view('livewire.admin.error-log');
    }
}
