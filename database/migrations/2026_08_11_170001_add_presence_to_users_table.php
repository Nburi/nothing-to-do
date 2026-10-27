<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Last heartbeat from a tab that actually had the app in the
            // foreground — see User::touchPresence(). Null means "never seen"
            // or "presence turned off".
            $table->timestamp('last_seen_at')->nullable()->after('remember_token');

            // Opt-out. Default true: an empty presence list would read as a
            // broken feature, and the only people who can see it are classmates
            // in a space this user chose to join.
            $table->boolean('show_presence')->default(true)->after('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['last_seen_at', 'show_presence']);
        });
    }
};
