<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Distinct from `last_seen_at` (presence — opt-out, and overwritten by the
     * current session's own heartbeat within seconds of loading, see
     * User::touchPresence()). This is a dedicated, always-on stamp of the
     * *previous* login, read once at the next login before it gets
     * overwritten, to detect a long-gap return — see
     * AuthenticatedSessionController::store() and User::WELCOME_BACK_AWAY_DAYS.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_login_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('last_login_at');
        });
    }
};
