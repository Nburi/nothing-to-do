<?php

namespace App\Services\Planning;

use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

/**
 * Refactor step 1.5 (R3): capture is one gesture. Whatever is typed or
 * dictated lands in the Inbox as it is; sorting it (list, size, dates) is
 * the daily sort's job (2.3). One place for every entry point: the capture
 * line (2.3), the REST endpoint for the iPhone Shortcut, the MCP tool.
 *
 * Titles up to MAX_LENGTH characters are kept whole (quality bar: 400
 * character titles). Whitespace is trimmed and runs of it collapse, so a
 * dictated line with a stray newline stays one readable title.
 */
final class Capture
{
    public const MAX_LENGTH = 500;

    public function inbox(User $user, mixed $text): Task
    {
        $clean = is_string($text) ? trim((string) preg_replace('/\s+/u', ' ', $text)) : $text;

        $data = Validator::make(['text' => $clean], [
            'text' => ['required', 'string', 'max:'.self::MAX_LENGTH],
        ], [
            'text.required' => __('capture.empty'),
            'text.string' => __('capture.empty'),
            'text.max' => __('capture.too_long', ['max' => self::MAX_LENGTH]),
        ])->validate();

        $last = Task::forUser($user)->inList('inbox')->max('sort_order');

        return $user->tasks()->create([
            'title' => $data['text'],
            'list' => 'inbox',
            'sort_order' => $last === null ? 0 : $last + 1,
        ]);
    }
}
