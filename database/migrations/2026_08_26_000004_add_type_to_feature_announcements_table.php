<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Distinguishes an announcement's flavour — info / maintenance / warning /
     * release — so a scheduled-downtime notice reads differently from a plain
     * "here's what's new" note. See App\Models\FeatureAnnouncement::TYPES.
     * Defaults every existing row to 'info', matching the toast's only look
     * before this column existed.
     */
    public function up(): void
    {
        Schema::table('feature_announcements', function (Blueprint $table) {
            $table->string('type')->default('info')->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('feature_announcements', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
