<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step 4.3: a group holds one list now and remembers it (`list`, filled for existing groups by
 * 2026_11_17_000004), so an empty group still knows where its new steps go. A group that became
 * a project points to it (`project_id`); such a group is no longer shown as a group, the row and
 * its note cards stay so nothing is lost and the step can be rolled back.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_groups', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('user_id')->constrained('projects')->nullOnDelete();
            $table->string('list', 20)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('task_groups', function (Blueprint $table) {
            $table->dropColumn('list');
            $table->dropConstrainedForeignId('project_id');
        });
    }
};
