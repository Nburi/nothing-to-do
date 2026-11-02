<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Planer's new day-granularity placement: a task lives on at most
     * one day (task_id unique), same "one contiguous slot" rule the old
     * block-based planner had. `source` distinguishes a manual drag from the
     * optional "Rest automatisch einplanen" convenience fill, mirroring
     * schedule_event_task_links.source — but unlike that column, 'auto' rows
     * here are real and ongoing, not a retired concept.
     */
    public function up(): void
    {
        Schema::create('task_day_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->unique()->constrained()->cascadeOnDelete();
            $table->date('planned_date');
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('source')->default('manual');
            $table->timestamps();

            $table->index('planned_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_day_plans');
    }
};
