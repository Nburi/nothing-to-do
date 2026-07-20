<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'pomodoro_work', 'pomodoro_short_break', 'pomodoro_long_break', 'pomodoro_long_every',
            ]);
        });

        Schema::table('event_categories', function (Blueprint $table) {
            $table->dropColumn('pomodoro_enabled');
        });

        Schema::table('schedule_events', function (Blueprint $table) {
            $table->dropColumn('pomodoro_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedSmallInteger('pomodoro_work')->default(25);
            $table->unsignedSmallInteger('pomodoro_short_break')->default(5);
            $table->unsignedSmallInteger('pomodoro_long_break')->default(15);
            $table->unsignedSmallInteger('pomodoro_long_every')->default(4);
        });

        Schema::table('event_categories', function (Blueprint $table) {
            $table->boolean('pomodoro_enabled')->default(false);
        });

        Schema::table('schedule_events', function (Blueprint $table) {
            $table->timestamp('pomodoro_started_at')->nullable();
        });
    }
};
