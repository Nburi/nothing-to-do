<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Which board column (inbox/todos/tasks) a project task surfaces
            // under while its project is the active emergency project. Only
            // ever consulted in that context — otherwise inert and ignored,
            // so nothing needs to reset it when emergency mode ends.
            $table->string('emergency_list', 20)->nullable()->after('project_id');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('emergency_list');
        });
    }
};
