<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // How many 25' Work-Sessions a Task is estimated to need. Set in the
            // Brief; null for To-Dos and unplanned Tasks.
            $table->unsignedSmallInteger('estimated_sessions')->nullable()->after('due_date');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('estimated_sessions');
        });
    }
};
