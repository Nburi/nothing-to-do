<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Recurring tasks. `repeat_rule` is one of Task::REPEAT_RULES (a plain
     * string, not a DB enum — same convention as `list`). When a repeating
     * task is completed, the next occurrence is created as a fresh task;
     * `repeated_from_id` points that successor back at the task that spawned
     * it, so un-completing the original can take its still-untouched
     * successor away again instead of leaving two copies behind.
     * nullOnDelete: deleting the original must never delete its successor.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('repeat_rule', 16)->nullable()->after('duration_minutes');
            $table->foreignId('repeated_from_id')->nullable()->after('repeat_rule')
                ->constrained('tasks')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['repeated_from_id']);
            $table->dropColumn(['repeat_rule', 'repeated_from_id']);
        });
    }
};
