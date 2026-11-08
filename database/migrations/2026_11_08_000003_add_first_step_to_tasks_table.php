<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Refactor step 1.3: "Start with: …" on every suggestion (CONVENTIONS 3). The
 * optional first step of a task; the daily sort (2.3) and the edit form fill it.
 * When it is empty the suggestion falls back to the first open checklist line
 * of the notes, so existing tasks with a checklist get one for free.
 *
 * Additive and nullable: every existing row stays as it is. It is also where
 * the old craft ideas' "where to begin" note lands when they move into Later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('first_step', 255)->nullable()->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('first_step');
        });
    }
};
