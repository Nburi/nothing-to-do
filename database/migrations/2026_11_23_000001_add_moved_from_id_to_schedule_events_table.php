<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step 4.1 (C21, Niels 2026-11-17): a block moved to another day "this week only" can go back to the
 * repeating event. Moving leaves a tombstone (a cancelled row of the series) on the old date and a plain
 * one-off on the new date; nothing tied the two together, so the one-off could not find its way back.
 * moved_from_id is that tie: the id of the tombstone, written when the block is moved.
 *
 * Additive and nullable: every existing row keeps null, and the engine and the old screens ignore the
 * column. Blocks moved before this migration have no tie and stay plain one-offs, as they were. It is a
 * plain integer without a foreign key on purpose: deleting the tombstone, by "Rückgängig" or because the
 * series changed, must neither cascade nor block; the editor checks that the tombstone is still there
 * before it offers the way back. down() only drops the column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_events', function (Blueprint $table) {
            $table->unsignedBigInteger('moved_from_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('schedule_events', function (Blueprint $table) {
            $table->dropColumn('moved_from_id');
        });
    }
};
