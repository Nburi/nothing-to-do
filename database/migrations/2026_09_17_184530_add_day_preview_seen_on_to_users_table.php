<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // The user's local calendar date they last opened the Tagesüberblick
            // (App\Livewire\DayPreview) — the one signal the silent header dot
            // reads to decide whether today's overview is still unseen. Mirrors
            // prepared_on's shape exactly (see the Vorbereitung migration).
            $table->date('day_preview_seen_on')->nullable()->after('prepare_prompt_dismissed_on');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('day_preview_seen_on');
        });
    }
};
