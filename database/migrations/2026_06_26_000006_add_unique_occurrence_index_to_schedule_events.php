<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_events', function (Blueprint $table) {
            // One materialised occurrence per (user, template, date). NULL template_id
            // (one-off events and detached copies) is exempt — NULLs never collide —
            // so this only guards recurring-series materialisation against races.
            $table->unique(['user_id', 'template_id', 'date'], 'schedule_events_occurrence_unique');
        });
    }

    public function down(): void
    {
        Schema::table('schedule_events', function (Blueprint $table) {
            $table->dropUnique('schedule_events_occurrence_unique');
        });
    }
};
