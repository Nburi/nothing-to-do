<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The user's header badge configuration: an ordered JSON list of
     * {key, enabled} rows, one per App\Services\HeaderBadges::CATALOG entry.
     * Null (the default for every existing and new account) means "use the
     * catalog's own default selection" — see HeaderBadges::preferenceRowsFor()
     * — so nothing has to backfill a row for accounts that never touch
     * Settings, and a future catalog addition also just falls back to its own
     * default until someone explicitly reorders/toggles it.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('header_badges')->nullable()->after('daily_task_goal');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('header_badges');
        });
    }
};
