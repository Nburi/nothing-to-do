<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Which day the "Vorbereitung" ritual targets: 'morning' plans the
            // already-running today, 'evening' (default) plans tomorrow.
            $table->string('prepare_time_of_day', 10)->default('evening')->after('notify_break_start');

            // 'off' | 'automatic' | 'fixed' — see User::prepareReminderDueTime().
            $table->string('prepare_reminder_mode', 10)->default('off')->after('prepare_time_of_day');

            // HH:MM, only meaningful when prepare_reminder_mode = 'fixed'.
            $table->string('prepare_reminder_time', 5)->nullable()->after('prepare_reminder_mode');

            // The user's local calendar date they last completed the ritual
            // (PrepareTomorrow::finish()) — the one signal every reminder path
            // reads to know whether today's Vorbereitung still needs doing.
            $table->date('prepared_on')->nullable()->after('prepare_reminder_time');

            // Dedupe stamps (local calendar date), so the per-minute scheduled
            // command and the in-app banner each fire at most once a day.
            $table->date('prepare_reminder_sent_on')->nullable()->after('prepared_on');
            $table->date('prepare_prompt_dismissed_on')->nullable()->after('prepare_reminder_sent_on');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'prepare_time_of_day', 'prepare_reminder_mode', 'prepare_reminder_time',
                'prepared_on', 'prepare_reminder_sent_on', 'prepare_prompt_dismissed_on',
            ]);
        });
    }
};
