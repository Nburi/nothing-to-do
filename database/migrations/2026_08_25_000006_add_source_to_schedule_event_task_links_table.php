<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_event_task_links', function (Blueprint $table) {
            // 'manual' backfill is correct: every row that can exist before this
            // migration was created by hand, via the event form's task picker —
            // the WorkPlanner algorithm ('auto') doesn't exist yet at this point
            // in the app's history.
            $table->string('source', 10)->default('manual')->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('schedule_event_task_links', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
