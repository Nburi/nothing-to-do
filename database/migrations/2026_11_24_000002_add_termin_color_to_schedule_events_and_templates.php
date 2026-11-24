<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step 4.1 (Niels, 2026-11-24: "for Termine I would also like the colour choice"): a Termin (a block
 * without a category) in the week plan can carry one of the five palette keys the categories use.
 *
 * The old `color` column cannot say "never chosen": it is NOT NULL with default 'contour', so an untouched
 * Termin and one that deliberately got Ocker look the same. termin_color is that missing bit: null means
 * the current look (the dark "Standard" block of the week editor), a key means the user chose it.
 *
 * Additive and nullable on both tables (a repeating Termin is an event template): every existing row
 * keeps null, so existing data looks exactly as before, and the old screens, API and MCP keep reading
 * `color` (the editor writes both; the models copy an explicit `color` of a category-less row into
 * termin_color, so a colour set through the API or MCP shows up in the grid too). Nothing is backfilled.
 * A plain string like `color`, validated against ScheduleEvent::EVENT_COLORS in the editor. down() drops the columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['schedule_events', 'event_templates'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->string('termin_color', 20)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['schedule_events', 'event_templates'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('termin_color');
            });
        }
    }
};
