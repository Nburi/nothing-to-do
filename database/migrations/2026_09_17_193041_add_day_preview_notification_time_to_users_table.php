<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Per-user trigger time for the morning "Dein Tag ist bereit" push —
            // moved out of config/day_preview.php (which still holds the title
            // pool) so each person can set their own, same shape as daily_reminder_time.
            $table->string('day_preview_notification_time', 5)->default('07:30')->after('day_preview_notification_sent_on');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('day_preview_notification_time');
        });
    }
};
