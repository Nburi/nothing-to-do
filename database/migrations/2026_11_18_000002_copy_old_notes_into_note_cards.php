<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Step 4.3b (R8): the old notes come back as note cards. Nothing is deleted or changed in the
 * old columns:
 * - a project's `brainstorm` (Markdown) becomes its first card, in no stage ("Lose");
 * - the note cards of a group that is still a group (`group_notes`) become its cards, in order.
 *   A group that became a project in 2026_11_17_000004 already has its notes in that project's
 *   brainstorm, so they are not copied twice.
 * Tags are read from the text like the app does (App\Services\Projects\NoteTags; the pattern is
 * copied here so this migration never changes with the app). Idempotent through
 * `notes.legacy_source`; the rollback removes only those cards (and tags left without a note).
 */
return new class extends Migration
{
    private const TAG = '/(?<![\p{L}\p{N}_&#\/])#(\p{L}[\p{L}\p{N}_-]{0,49})/u';

    public function up(): void
    {
        foreach (DB::table('projects')->whereNotNull('brainstorm')->orderBy('id')->get() as $project) {
            if (trim((string) $project->brainstorm) === '') {
                continue;
            }
            $this->card('brainstorm:'.$project->id, $project->user_id, ['project_id' => $project->id], $project->brainstorm, 0);
        }

        $groups = DB::table('task_groups')->whereNull('project_id')->orderBy('id')->get();
        foreach ($groups as $group) {
            $notes = DB::table('group_notes')->where('task_group_id', $group->id)->orderBy('sort_order')->orderBy('id')->get();
            foreach ($notes->values() as $i => $note) {
                if (trim((string) $note->content) === '') {
                    continue;
                }
                $this->card('group_note:'.$note->id, $group->user_id, ['group_id' => $group->id], $note->content, $i);
            }
        }
    }

    public function down(): void
    {
        DB::table('notes')->whereNotNull('legacy_source')->delete();
        DB::table('tags')->whereNotExists(fn ($q) => $q->from('note_tag')->whereColumn('note_tag.tag_id', 'tags.id'))->delete();
    }

    private function card(string $source, int $userId, array $owner, string $body, int $order): void
    {
        if (DB::table('notes')->where('legacy_source', $source)->exists()) {
            return;
        }

        DB::transaction(function () use ($source, $userId, $owner, $body, $order) {
            $noteId = DB::table('notes')->insertGetId([
                ...$owner,
                'user_id' => $userId,
                'body' => $body,
                'sort_order' => $order,
                'legacy_source' => $source,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            preg_match_all(self::TAG, $body, $m);
            foreach (array_unique(array_map(fn ($t) => mb_strtolower($t), $m[1])) as $name) {
                $tagId = DB::table('tags')->where('user_id', $userId)->where('name', $name)->value('id')
                    ?? DB::table('tags')->insertGetId(['user_id' => $userId, 'name' => $name, 'created_at' => now(), 'updated_at' => now()]);
                DB::table('note_tag')->insertOrIgnore(['note_id' => $noteId, 'tag_id' => $tagId]);
            }
        });
    }
};
