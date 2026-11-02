<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // The project currently in "emergency mode" (null = not active).
            // Deleting the project falls back to null so the dashboard
            // silently returns to normal instead of pointing at nothing.
            $table->foreignId('emergency_project_id')
                ->nullable()
                ->after('id')
                ->constrained('projects')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['emergency_project_id']);
            $table->dropColumn('emergency_project_id');
        });
    }
};
