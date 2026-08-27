@php
    $statusToneClasses = fn (string $tone) => match ($tone) {
        'contour' => 'bg-contour-soft text-contour',
        'forest' => 'bg-forest-soft text-forest',
        'faint' => 'bg-paper text-ink-faint border border-line',
        default => 'bg-line text-ink-soft',
    };
@endphp
<div class="mx-auto max-w-2xl px-5 py-10 sm:px-6">
    <div class="mb-1.5 flex items-center gap-3">
        <a href="{{ route('help') }}" wire:navigate class="grid h-8 w-8 place-items-center rounded-card text-ink-faint transition hover:bg-surface hover:text-ink" aria-label="Zurück zur Hilfe">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
        </a>
        <h1 class="text-xl font-medium text-ink">Feedback &amp; Support</h1>
    </div>
    <p class="mb-6 pl-11 text-sm text-ink-soft">Geht direkt an die Admins. Den Status siehst du hier weiter unten.</p>

    <div class="mb-10 rounded-card border border-line bg-surface p-6 shadow-map">
        @if ($justSubmitted)
            <div class="flex items-center gap-2 text-sm font-medium text-forest">
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m4 10 4 4 8-9"/></svg>
                Danke — deine Nachricht ist angekommen.
            </div>
            <button type="button" wire:click="$set('justSubmitted', false)" class="mt-3 text-sm text-overprint transition hover:underline">Noch etwas melden</button>
        @else
            <form wire:submit="submit" class="space-y-4">
                <div class="flex flex-wrap gap-1.5">
                    @foreach (\App\Models\SupportRequest::TYPES as $key => $meta)
                        <button type="button" wire:click="$set('formType', '{{ $key }}')" @class(['rounded-[0.45rem] px-3.5 py-1.5 text-sm transition', 'bg-ink text-white shadow-sm' => $formType === $key, 'bg-paper text-ink-soft hover:text-ink' => $formType !== $key])>{{ $meta['label'] }}</button>
                    @endforeach
                </div>
                <div>
                    <label for="formSubject" class="mb-1.5 block text-sm font-medium text-ink">Betreff</label>
                    <input id="formSubject" type="text" wire:model="formSubject" maxlength="255" placeholder="Ein Satz reicht" class="block w-full rounded-card border border-line bg-paper px-3 py-2 text-sm text-ink focus:border-overprint focus:outline-none focus:ring-0" />
                    @error('formSubject') <p class="mt-1.5 text-xs text-signal">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="formMessage" class="mb-1.5 block text-sm font-medium text-ink">Nachricht</label>
                    <textarea id="formMessage" wire:model="formMessage" rows="4" maxlength="5000" placeholder="Was ist passiert, was hättest du erwartet?" class="block w-full resize-y rounded-card border border-line bg-paper px-3 py-2 text-sm text-ink focus:border-overprint focus:outline-none focus:ring-0"></textarea>
                    @error('formMessage') <p class="mt-1.5 text-xs text-signal">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="rounded-card bg-forest px-4 py-2 text-sm font-medium text-white transition hover:brightness-110 active:scale-[0.98]">Absenden</button>
            </form>
        @endif
    </div>

    <h2 class="mb-3 text-base font-medium text-ink">Meine Anfragen</h2>
    <div class="space-y-2">
        @forelse ($this->myRequests as $request)
            <div wire:key="req-{{ $request->id }}" class="rounded-card border border-line bg-surface p-4 shadow-map">
                <div class="mb-1.5 flex flex-wrap items-center gap-2">
                    <span class="rounded-full bg-overprint-soft px-1.5 py-0.5 text-[10px] font-medium leading-none text-overprint">{{ $request->typeLabel() }}</span>
                    <span @class(['rounded-full px-1.5 py-0.5 text-[10px] font-medium leading-none', $statusToneClasses($request->statusMeta()['tone'])])>{{ $request->statusLabel() }}</span>
                </div>
                <p class="text-sm font-medium text-ink">{{ $request->subject }}</p>
                <p class="mt-1 text-xs text-ink-faint">Eingereicht am {{ $request->created_at->isoFormat('D. MMMM YYYY') }}</p>
                @if ($request->response)
                    <div class="mt-2.5 rounded-card border-l-2 border-forest bg-paper px-3 py-2 text-[13px] text-ink-soft">
                        <b class="font-semibold text-ink">Antwort:</b> {{ $request->response }}
                    </div>
                @endif
            </div>
        @empty
            <p class="text-sm text-ink-faint">Noch keine Anfragen — alles, was du hier einreichst, taucht auf dieser Liste auf.</p>
        @endforelse
    </div>
</div>
