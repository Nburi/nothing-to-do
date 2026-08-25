<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // null = never opened the tutorial (a brand-new account, or an
            // existing account that predates this feature — neither is ever
            // forced through it after the fact, only a fresh registration
            // auto-redirects here, see RegisteredUserController). Stamped by
            // both finishing and skipping — either counts as "seen it" — and
            // re-stamped on every replay, so it doubles as "last viewed on".
            $table->timestamp('onboarding_completed_at')->nullable()->after('default_page');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('onboarding_completed_at');
        });
    }
};
