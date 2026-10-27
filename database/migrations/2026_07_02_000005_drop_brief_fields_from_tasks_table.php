<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'planned_for']);
            $table->dropColumn(['estimated_sessions', 'planned_for']);
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->unsignedSmallInteger('estimated_sessions')->nullable()->after('due_date');
            $table->date('planned_for')->nullable()->after('estimated_sessions');
            $table->index(['user_id', 'planned_for']);
        });
    }
};
