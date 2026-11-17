<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Nicht vergessen": a dated note that is deliberately not a task — it sits
     * in no list, counts for no goal or streak and has no estimate. Just a
     * title and the day it matters (take the gym shoes to school on Wednesday).
     *
     * The two notify_* flags choose which pushes this reminder gets (the evening
     * before / the day itself); notified_*_at stamp each push once it has gone
     * out, so a delayed cron tick still sends it and a restarted one never
     * sends it twice. acknowledged_at is "Hab ich": it ends all further pushes.
     */
    public function up(): void
    {
        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 120);
            $table->date('remind_on');
            $table->boolean('notify_evening_before')->default(true);
            $table->boolean('notify_on_day')->default(true);
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('notified_before_at')->nullable();
            $table->timestamp('notified_on_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'remind_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminders');
    }
};
