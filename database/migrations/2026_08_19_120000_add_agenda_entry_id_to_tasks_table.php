<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Links a task back to the Agenda homework entry it was dragged/swiped in
     * from (see TaskBoard::promoteHomeworkToday()). Deliberately nullOnDelete,
     * same as group_id/project_id: deleting the Agenda entry must not take the
     * task with it, it just becomes a normal, unlinked task.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('agenda_entry_id')->nullable()->after('group_id')
                ->constrained('agenda_entries')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['agenda_entry_id']);
            $table->dropColumn('agenda_entry_id');
        });
    }
};
