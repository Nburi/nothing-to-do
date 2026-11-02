<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lets a Pomodoro-enabled category point the focus card's task suggestion
     * at something specific instead of the generic cross-app logic —
     * `task_source` picks which of the mutually-exclusive targets below is
     * active ('project'|'group'|'tasks'|'agenda_entry'|'agenda_generic'|'text';
     * 'tasks' uses the category_task_links pivot instead of a column here).
     * All target FKs are nullOnDelete, same as category_id on ScheduleEvent/
     * EventTemplate: deleting the linked project/group/agenda entry must not
     * break the category, it just reverts to having no suggestion source.
     */
    public function up(): void
    {
        Schema::table('event_categories', function (Blueprint $table) {
            $table->string('task_source')->nullable()->after('pomodoro_enabled');
            $table->foreignId('linked_project_id')->nullable()->after('task_source')
                ->constrained('projects')->nullOnDelete();
            $table->foreignId('linked_group_id')->nullable()->after('linked_project_id')
                ->constrained('task_groups')->nullOnDelete();
            $table->foreignId('linked_agenda_entry_id')->nullable()->after('linked_group_id')
                ->constrained('agenda_entries')->nullOnDelete();
            $table->string('linked_text')->nullable()->after('linked_agenda_entry_id');
        });
    }

    public function down(): void
    {
        Schema::table('event_categories', function (Blueprint $table) {
            $table->dropForeign(['linked_project_id']);
            $table->dropForeign(['linked_group_id']);
            $table->dropForeign(['linked_agenda_entry_id']);
            $table->dropColumn(['task_source', 'linked_project_id', 'linked_group_id', 'linked_agenda_entry_id', 'linked_text']);
        });
    }
};
