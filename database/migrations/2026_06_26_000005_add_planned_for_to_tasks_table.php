<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // The day the Brief planned this task for. Decoupled from is_today so a
            // plan made in the evening surfaces on the right day without polluting
            // today's focus area until that day arrives.
            $table->date('planned_for')->nullable()->after('estimated_sessions');
            $table->index(['user_id', 'planned_for']);
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'planned_for']);
            $table->dropColumn('planned_for');
        });
    }
};
