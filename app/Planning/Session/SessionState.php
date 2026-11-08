<?php

namespace App\Planning\Session;

/**
 * The server-owned state of a user's session (risk C14). Stored as is; every
 * device renders from it, so a reload or a second device sees the same timer.
 * Instants are unix timestamps, which keeps the state timezone-free and
 * trivially serialisable.
 */
final readonly class SessionState
{
    /** @param  list<int>  $doneTaskIds  tasks finished on $counterDate */
    public function __construct(
        public Phase $phase,
        public ?int $taskId = null,
        public ?int $phaseStartedAt = null,
        public ?int $pausedAt = null,
        public int $pausedSeconds = 0,
        public int $sessionsToday = 0,
        public ?string $counterDate = null,
        public array $doneTaskIds = [],
    ) {}

    public static function idle(): self
    {
        return new self(Phase::Idle);
    }

    public function isPaused(): bool
    {
        return $this->pausedAt !== null;
    }

    public function with(mixed ...$changes): self
    {
        return new self(...array_merge(get_object_vars($this), $changes));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['phase' => $this->phase->value] + get_object_vars($this);
    }

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            Phase::from($data['phase']),
            $data['taskId'] ?? null,
            $data['phaseStartedAt'] ?? null,
            $data['pausedAt'] ?? null,
            (int) ($data['pausedSeconds'] ?? 0),
            (int) ($data['sessionsToday'] ?? 0),
            $data['counterDate'] ?? null,
            array_map('intval', $data['doneTaskIds'] ?? []),
        );
    }
}
