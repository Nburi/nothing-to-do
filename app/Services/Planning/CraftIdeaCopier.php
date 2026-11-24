<?php

namespace App\Services\Planning;

use App\Models\CraftIdea;
use App\Models\Task;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Refactor step 3.4 (R6, R8; docs/REFACTOR_PLAN.md § 3 "craft_ideas → Later items, copied"):
 * copies every old Bastelidee once into a Later task. Run at the switch (step 5.4) through
 * `php artisan app:copy-craft-ideas`, with `--dry-run` first.
 *
 * - Non-destructive: craft_ideas is only read, never changed or deleted.
 * - Idempotent: craft_idea_copies links each copied idea to its task; a second run copies only
 *   ideas that were added since. A copied task the user deleted is not brought back.
 * - Faithful: title as is; where_to_begin becomes first_step ("Starte mit"). Longer than the
 *   column (255) it is shortened there and kept whole in notes. A done idea becomes a finished
 *   Later task (completed_at = when it was last changed). The dates of the idea are kept.
 * - Order: after the user's existing Later items, in the order the ideas were created.
 * - One transaction per user: a failure leaves that user exactly as before.
 */
final class CraftIdeaCopier
{
    public const FIRST_STEP_MAX = 255;

    /**
     * With $dryRun, `copied` counts what would be copied and nothing is written.
     *
     * @return array{users: int, ideas: int, already: int, copied: int, long_notes: int}
     */
    public function run(bool $dryRun = false, ?int $userId = null): array
    {
        $stats = ['users' => 0, 'ideas' => 0, 'already' => 0, 'copied' => 0, 'long_notes' => 0];

        $userIds = CraftIdea::query()
            ->when($userId !== null, fn ($q) => $q->where('user_id', $userId))
            ->distinct()->orderBy('user_id')->pluck('user_id');

        foreach ($userIds as $uid) {
            $stats['users']++;
            $copied = DB::table('craft_idea_copies')->where('user_id', $uid)->pluck('craft_idea_id')->all();
            $ideas = CraftIdea::query()->where('user_id', $uid)->orderBy('created_at')->orderBy('id')->get();
            $todo = $ideas->reject(fn (CraftIdea $i) => in_array($i->id, $copied, true));

            $stats['ideas'] += $ideas->count();
            $stats['already'] += $ideas->count() - $todo->count();
            $stats['copied'] += $todo->count();
            $stats['long_notes'] += $todo->filter(fn (CraftIdea $i) => mb_strlen(trim((string) $i->where_to_begin)) > self::FIRST_STEP_MAX)->count();

            if ($dryRun || $todo->isEmpty()) {
                continue;
            }

            DB::transaction(function () use ($uid, $todo) {
                $order = (int) Task::query()->where('user_id', $uid)->where('list', Task::LATER)->max('sort_order');

                foreach ($todo as $idea) {
                    $task = Task::forceCreate($this->attributes($idea, ++$order));
                    DB::table('craft_idea_copies')->insert([
                        'craft_idea_id' => $idea->id,
                        'user_id' => $uid,
                        'task_id' => $task->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
        }

        return $stats;
    }

    /** @return array<string, mixed> */
    private function attributes(CraftIdea $idea, int $sortOrder): array
    {
        $begin = trim((string) $idea->where_to_begin);
        $long = mb_strlen($begin) > self::FIRST_STEP_MAX;

        return [
            'user_id' => $idea->user_id,
            'title' => $idea->title,
            'list' => Task::LATER,
            'first_step' => $begin === '' ? null : ($long ? Str::limit($begin, self::FIRST_STEP_MAX - 1, '…') : $begin),
            'notes' => $long ? $begin : null,
            'is_completed' => $idea->is_done,
            'completed_at' => $idea->is_done ? $idea->updated_at : null,
            'sort_order' => $sortOrder,
            'created_at' => $idea->created_at,
            'updated_at' => $idea->updated_at,
        ];
    }
}
