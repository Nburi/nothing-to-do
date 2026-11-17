<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Morning push ("Dein Tag ist bereit") once config('day_preview.notification.time')
            // has passed locally — trigger time and title live in config/day_preview.php
            // (editable without touching code), the body is always a live summary built
            // from DayPreviewData::notificationSummary(), never a static string.
            $table->boolean('notify_day_preview')->default(false)->after('day_preview_seen_on');
            $table->date('day_preview_notification_sent_on')->nullable()->after('notify_day_preview');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['notify_day_preview', 'day_preview_notification_sent_on']);
        });
    }
};
