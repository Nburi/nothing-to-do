<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Superseded by schedule_event_task_links (see that migration) — an entry
     * now binds several tasks, not one. This feature shipped in the same
     * session and was never merged/deployed, so there is no production data
     * to preserve; confirmed with the user before dropping (CLAUDE.md §8).
     */
    public function up(): void
    {
        Schema::table('schedule_events', function (Blueprint $table) {
            $table->dropForeign(['linked_task_id']);
            $table->dropColumn('linked_task_id');
        });
    }

    public function down(): void
    {
        Schema::table('schedule_events', function (Blueprint $table) {
            $table->foreignId('linked_task_id')->nullable()->after('category_id')
                ->constrained('tasks')->nullOnDelete();
        });
    }
};
