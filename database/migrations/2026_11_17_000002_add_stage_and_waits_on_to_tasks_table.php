<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step 4.3: a project step may belong to a stage, and in the rare case it waits on one other
 * task ("wartet auf …"). Both are nullable and fall back to null when the other row goes away,
 * so deleting a stage or a task never deletes a step.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('stage_id')->nullable()->after('group_id')->constrained('project_stages')->nullOnDelete();
            $table->foreignId('waits_on_id')->nullable()->after('stage_id')->constrained('tasks')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('waits_on_id');
            $table->dropConstrainedForeignId('stage_id');
        });
    }
};
