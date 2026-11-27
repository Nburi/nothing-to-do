<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Step 5.4 (Niels, decision "catlinks", 2026-11-27): the category links of the old app, carried over.
 *
 * - A link to a project or a group (`task_source` project / group, `linked_project_id` / `linked_group_id`)
 *   needs no change: the pick engine reads exactly these columns now ("suggest this project's or group's
 *   next steps in this block").
 * - A link to an Agenda entry or the class Agenda has no equivalent in the new app. It becomes a text link
 *   (`task_source` text, `linked_text` the entry's title, "Agenda" for the generic one), so the category
 *   keeps a visible hint. Nothing is deleted: `linked_agenda_entry_id` stays, and the original source is
 *   written to `legacy_task_source` so `down` can put everything back exactly. When the label was written
 *   by this migration the value carries a `+label` suffix ("agenda_entry+label"); a label that was already
 *   there is never touched, neither by `up` nor by `down`.
 *
 * A category that already has a `linked_text` keeps it; a row that already has a `legacy_task_source` is left alone.
 */
return new class extends Migration
{
    private const DROPPED = ['agenda_entry', 'agenda_generic'];

    public function up(): void
    {
        Schema::table('event_categories', function (Blueprint $table) {
            $table->string('legacy_task_source')->nullable()->after('linked_text');
        });

        $rows = DB::table('event_categories')->whereIn('task_source', self::DROPPED)->whereNull('legacy_task_source')->get();

        foreach ($rows as $row) {
            $title = $row->linked_agenda_entry_id !== null
                ? DB::table('agenda_entries')->where('id', $row->linked_agenda_entry_id)->value('title')
                : null;

            $hasLabel = $row->linked_text !== null && trim($row->linked_text) !== '';

            DB::table('event_categories')->where('id', $row->id)->update([
                'legacy_task_source' => $row->task_source.($hasLabel ? '' : '+label'),
                'task_source' => 'text',
                'linked_text' => $hasLabel ? $row->linked_text : mb_substr($title ?? 'Agenda', 0, 255),
            ]);
        }
    }

    public function down(): void
    {
        foreach (DB::table('event_categories')->whereNotNull('legacy_task_source')->get() as $row) {
            [$source, $wroteLabel] = [str_replace('+label', '', $row->legacy_task_source), str_ends_with($row->legacy_task_source, '+label')];

            DB::table('event_categories')->where('id', $row->id)->update([
                // Only a link that is still the text link we made goes back; one the user has changed since stays.
                'task_source' => $row->task_source === 'text' ? $source : $row->task_source,
                'linked_text' => $wroteLabel && $row->task_source === 'text' ? null : $row->linked_text,
            ]);
        }

        Schema::table('event_categories', function (Blueprint $table) {
            $table->dropColumn('legacy_task_source');
        });
    }
};
