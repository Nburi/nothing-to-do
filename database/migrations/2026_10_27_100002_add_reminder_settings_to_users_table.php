<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When reminder pushes go out (see App\Services\Reminders). The evening
     * push for tomorrow's reminders and the fallback time for today's are
     * clock times in the user's own local day; "smart timing" lets a reminder
     * for today arrive earlier than that fallback when the first block of the
     * day (plus its travel time) starts before it.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('reminder_evening_time', 5)->default('19:00');
            $table->string('reminder_morning_time', 5)->default('07:00');
            $table->boolean('reminder_smart_timing')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['reminder_evening_time', 'reminder_morning_time', 'reminder_smart_timing']);
        });
    }
};
