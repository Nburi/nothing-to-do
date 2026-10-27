<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lets one specific schedule entry (not its template — a recurring block's
     * occurrences are otherwise independent once materialised, see Wochenplan)
     * point at a single task. More specific than a category's own task link
     * (EventCategory::task_source): when both exist on the same running
     * session, this one wins — see TaskSuggestor. nullOnDelete, same safety
     * pattern as every other linked_* column in this app: deleting the task
     * reverts the entry to having no link, it never breaks.
     */
    public function up(): void
    {
        Schema::table('schedule_events', function (Blueprint $table) {
            $table->foreignId('linked_task_id')->nullable()->after('category_id')
                ->constrained('tasks')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('schedule_events', function (Blueprint $table) {
            $table->dropForeign(['linked_task_id']);
            $table->dropColumn('linked_task_id');
        });
    }
};
