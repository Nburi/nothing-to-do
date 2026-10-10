<?php

namespace App\Mcp\Tools\Concerns;

use App\Models\ScheduleEvent;

/**
 * Shared bits of the Zeitplan tools: they all live behind the `schedule`
 * module, and several share the same field schemas.
 */
trait ScheduleToolDefaults
{
    public function requiredModule(): ?string
    {
        return 'schedule';
    }

    /** @return array<string, mixed> */
    protected static function timeSchema(string $what): array
    {
        return ['type' => 'string', 'pattern' => '^([01]\d|2[0-3]):[0-5]\d$', 'description' => $what.' as HH:MM (24h).'];
    }

    /** @return array<string, mixed> */
    protected static function colorSchema(): array
    {
        return ['type' => 'string', 'enum' => ScheduleEvent::EVENT_COLORS, 'description' => 'Colour token of an appointment (a category block uses its category\'s colour).'];
    }

    /** @return array<string, mixed> */
    protected static function bufferSchema(string $which): array
    {
        return ['type' => 'integer', 'minimum' => 0, 'maximum' => 240, 'description' => $which.' in minutes (travel/buffer time, shown as a hatched extension of the block).'];
    }
}
