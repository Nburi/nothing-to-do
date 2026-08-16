<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_events', function (Blueprint $table) {
            // Stamped once the "5 minutes before" push has been sent. A
            // separate column from notified_at: the two notifications are
            // independent opt-ins and one event can fire both.
            $table->timestamp('notified_upcoming_at')->nullable()->after('notified_at');
        });
    }

    public function down(): void
    {
        Schema::table('schedule_events', function (Blueprint $table) {
            $table->dropColumn('notified_upcoming_at');
        });
    }
};
