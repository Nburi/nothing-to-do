{{-- Member list for one class — the detail half of the Klassen sheet.
     Expects $this->managedSpace and $this->managedMembers. --}}
@php
    $space = $this->managedSpace;
    $iAmOwner = $space->owner_id === auth()->id();
    $online = $this->managedMembers->filter(fn ($m) => $m->isOnline())->count();
@endphp

{{-- Presence is only true for ~2 minutes, so a static list would go stale while
     you look at it. `.visible` matters: this whole sheet lives in the DOM with
     display:none when closed, and a hidden panel must not keep polling. --}}
<div wire:poll.30s.visible>
    <div class="mb-3 flex items-baseline justify-between gap-3 px-0.5">
        <span class="text-[12px] text-ink-faint">
            {{ $this->managedMembers->count() }} {{ $this->managedMembers->count() === 1 ? 'Mitglied' : 'Mitglieder' }}
        </span>
        @if ($online > 0)
            <span class="flex items-center gap-1.5 text-[12px] text-forest">
                <span class="h-1.5 w-1.5 rounded-full bg-forest" aria-hidden="true"></span>
                {{ $online }} gerade online
            </span>
        @endif
    </div>

    <div class="space-y-1">
        @foreach ($this->managedMembers as $member)
            @php
                $isMe = $member->id === auth()->id();
                $isSpaceOwner = $member->id === $space->owner_id;
                $isOnline = $member->isOnline();
                $seen = $member->lastSeenLabel();
            @endphp
            <div wire:key="member-{{ $space->id }}-{{ $member->id }}" class="group/member flex items-center gap-2.5 rounded-card px-2 py-2 transition hover:bg-paper">
                {{-- Avatar with the presence dot attached, rather than a separate
                     column: it keeps the row readable at a glance and survives a
                     long name without the dot drifting away from the person. --}}
                <div class="relative flex-none">
                    <span class="grid h-8 w-8 place-items-center rounded-full bg-forest-soft text-[12px] font-medium text-forest">
                        {{ mb_strtoupper(mb_substr($member->name, 0, 1)) }}
                    </span>
                    @if ($isOnline)
                        <span
                            class="absolute -bottom-0.5 -right-0.5 h-2.5 w-2.5 rounded-full border-2 border-surface bg-forest"
                            title="Gerade online"
                        ></span>
                    @endif
                </div>

                <div class="min-w-0 flex-1">
                    <p class="truncate text-[13.5px] text-ink">
                        {{ $member->name }}@if ($isMe)<span class="text-ink-faint"> (du)</span>@endif
                    </p>
                    <p class="truncate text-[11.5px] {{ $isOnline ? 'text-forest' : 'text-ink-faint' }}">
                        @if ($isSpaceOwner)
                            <span class="text-contour">Besitzer</span>
                            @if ($isOnline) · online @elseif ($seen) · {{ $seen }} @endif
                        @elseif ($isOnline)
                            online
                        @elseif ($seen)
                            {{ $seen }}
                        @else
                            {{-- No label at all: either they've never been seen, or they
                                 turned presence off. Both are "nothing to report", and
                                 saying "offline" would leak the difference. --}}
                            &nbsp;
                        @endif
                    </p>
                </div>

                @if ($iAmOwner && ! $isMe)
                    <div class="flex flex-none items-center gap-0.5">
                        <button
                            type="button"
                            x-data="{ armed: false, _t: null }"
                            @click="if (armed) { $wire.transferOwnership({{ $space->id }}, {{ $member->id }}); clearTimeout(_t); armed = false; } else { armed = true; clearTimeout(_t); _t = setTimeout(() => armed = false, 2000); }"
                            @click.outside="armed = false; clearTimeout(_t)"
                            @keydown.escape.window="armed = false; clearTimeout(_t)"
                            :class="armed ? 'bg-contour text-white opacity-100' : 'text-ink-faint opacity-60 hover:bg-contour-soft hover:text-contour group-hover/member:opacity-100'"
                            class="rounded-card px-2 py-1 text-[11px] transition"
                            title="{{ $member->name }} zum Besitzer machen"
                        ><span x-text="armed ? 'Sicher?' : 'Besitz'"></span></button>

                        <button
                            type="button"
                            x-data="{ armed: false, _t: null }"
                            @click="if (armed) { $wire.removeMember({{ $space->id }}, {{ $member->id }}); clearTimeout(_t); armed = false; } else { armed = true; clearTimeout(_t); _t = setTimeout(() => armed = false, 2000); }"
                            @click.outside="armed = false; clearTimeout(_t)"
                            @keydown.escape.window="armed = false; clearTimeout(_t)"
                            :class="armed ? 'bg-signal text-white opacity-100' : 'text-ink-faint opacity-60 hover:bg-signal-soft hover:text-signal group-hover/member:opacity-100'"
                            class="grid h-7 w-7 place-items-center rounded-card transition"
                            aria-label="{{ $member->name }} aus der Klasse entfernen"
                        >
                            <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                <path d="M3 8h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                            </svg>
                        </button>
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    <div class="mt-4 border-t border-line pt-3">
        @if ($iAmOwner)
            <p class="px-0.5 text-[11.5px] leading-relaxed text-ink-faint">
                Als Besitzer kannst du Mitglieder entfernen oder den Besitz abgeben. Einträge, die
                jemand geschrieben hat, bleiben beim Entfernen in der Klasse.
            </p>
        @else
            <p class="px-0.5 text-[11.5px] leading-relaxed text-ink-faint">
                Nur {{ $space->owner->name ?? 'der Besitzer' }} kann Mitglieder entfernen. Du kannst die
                Klasse jederzeit selbst verlassen.
            </p>
        @endif
    </div>
</div>
