@php
    $statusToneClasses = fn (string $tone) => match ($tone) {
        'contour' => 'bg-contour-soft text-contour',
        'forest' => 'bg-forest-soft text-forest',
        'faint' => 'bg-paper text-ink-faint border border-line',
        default => 'bg-line text-ink-soft',
    };
@endphp
<div class="mx-auto max-w-3xl px-5 py-10 sm:px-6">
    <div class="mb-5 flex items-center gap-3">
        <a href="{{ url('/app') }}" class="grid h-8 w-8 place-items-center rounded-card text-ink-faint transition hover:bg-surface hover:text-ink" aria-label="Zurück zum Board" wire:navigate>
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
        </a>
        <h1 class="text-xl font-medium text-ink">Feedback &amp; Support</h1>
    </div>

    <div class="mb-6 flex flex-wrap gap-1.5">
        <button type="button" wire:click="setStatusFilter(null)" @class(['rounded-full px-3.5 py-1.5 text-sm transition', 'bg-ink text-white' => $statusFilter === null, 'bg-surface text-ink-soft hover:text-ink' => $statusFilter !== null])>Alle · {{ $this->statusCounts['all'] }}</button>
        @foreach (\App\Models\SupportRequest::STATUSES as $key => $meta)
            <button type="button" wire:click="setStatusFilter('{{ $key }}')" @class(['rounded-full px-3.5 py-1.5 text-sm transition', 'bg-ink text-white' => $statusFilter === $key, 'bg-surface text-ink-soft hover:text-ink' => $statusFilter !== $key])>{{ $meta['label'] }} · {{ $this->statusCounts[$key] }}</button>
        @endforeach
    </div>

    <div class="space-y-2">
        @forelse ($this->requests as $request)
            <div wire:key="req-{{ $request->id }}" class="rounded-card border border-line bg-surface p-4 shadow-map sm:p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <div class="mb-1 flex flex-wrap items-center gap-2">
                            <span class="rounded-full bg-overprint-soft px-1.5 py-0.5 text-[10px] font-medium leading-none text-overprint">{{ $request->typeLabel() }}</span>
                            <span class="text-xs text-ink-faint">von {{ $request->user->name }} · {{ $request->created_at->isoFormat('D.M.YYYY') }}</span>
                        </div>
                        <p class="text-sm font-medium text-ink">{{ $request->subject }}</p>
                        <p class="mt-1 text-sm text-ink-soft leading-relaxed">{{ $request->message }}</p>
                    </div>
                    <select wire:change="setStatus({{ $request->id }}, $event.target.value)" class="flex-none rounded-card border border-line bg-paper px-2.5 py-1.5 text-xs font-medium text-ink focus:border-overprint focus:outline-none focus:ring-0">
                        @foreach (\App\Models\SupportRequest::STATUSES as $key => $meta)
                            <option value="{{ $key }}" @selected($request->status === $key)>{{ $meta['label'] }}</option>
                        @endforeach
                    </select>
                </div>

                @if ($respondingId === $request->id)
                    <div class="mt-3 border-t border-line pt-3">
                        <textarea wire:model="responseDraft" rows="2" placeholder="Kurze Antwort an die einreichende Person (optional)…" class="block w-full resize-none rounded-card border border-line bg-paper px-3 py-2 text-sm text-ink placeholder:text-ink-faint focus:border-overprint focus:outline-none focus:ring-0"></textarea>
                        <div class="mt-2 flex items-center gap-2">
                            <button type="button" wire:click="saveResponse" class="rounded-card bg-forest px-3.5 py-1.5 text-xs font-medium text-white transition hover:brightness-110">Speichern</button>
                            <button type="button" wire:click="cancelResponding" class="text-xs text-ink-soft transition hover:text-ink">Abbrechen</button>
                        </div>
                    </div>
                @else
                    <div class="mt-3 border-t border-line pt-3">
                        @if ($request->response)
                            <p class="mb-2 text-[13px] text-ink-soft"><b class="font-semibold text-ink">Antwort:</b> {{ $request->response }}</p>
                        @endif
                        <button type="button" wire:click="startResponding({{ $request->id }})" class="text-xs text-overprint transition hover:underline">{{ $request->response ? 'Antwort bearbeiten' : 'Antworten' }}</button>
                    </div>
                @endif
            </div>
        @empty
            <p class="text-sm text-ink-faint">Nichts hier.</p>
        @endforelse
    </div>
</div>
