<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Brief-generated rows have no place in the category model — delete them
        // while the `type` column that identifies them still exists.
        DB::table('schedule_events')->whereIn('type', ['work_session', 'todo_session', 'break'])->delete();

        Schema::table('schedule_events', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('template_id')
                ->constrained('event_categories')->nullOnDelete();
            // When the user actually taps "Start" on a category's Pomodoro focus
            // timer — never set just because the block's scheduled time arrived.
            $table->timestamp('pomodoro_started_at')->nullable()->after('is_cancelled');
        });

        Schema::table('schedule_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('suggested_task_id');
            $table->dropColumn(['type', 'source']);
        });
    }

    public function down(): void
    {
        Schema::table('schedule_events', function (Blueprint $table) {
            $table->string('type')->default('appointment')->after('template_id');
            $table->string('source')->default('manual')->after('color');
            $table->foreignId('suggested_task_id')->nullable()->after('template_id')
                ->constrained('tasks')->nullOnDelete();
        });

        Schema::table('schedule_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
            $table->dropColumn('pomodoro_started_at');
        });
    }
};
