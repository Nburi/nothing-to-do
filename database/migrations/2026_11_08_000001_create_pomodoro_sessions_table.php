<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Refactor step 1.7: the server-owned session state (risk C14). One row per
 * user holding App\Planning\Session\SessionState as JSON; every device renders
 * from it, so a reload or the phone shows the same timer as the laptop.
 *
 * New table, nothing to migrate: the old per-event Pomodoro columns on
 * schedule_events stay untouched and keep serving the old screens until they
 * are retired (risk C11).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pomodoro_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('state');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pomodoro_sessions');
    }
};
