<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // UTC offset in hours (standard/winter time, no DST applied). Defaults to
            // 0 so existing behaviour is unchanged until the user configures it.
            $table->integer('timezone_offset')->default(0)->after('pomodoro_long_every');
            // Whether to add +1 hour automatically while European DST is in effect.
            $table->boolean('timezone_auto_dst')->default(false)->after('timezone_offset');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['timezone_offset', 'timezone_auto_dst']);
        });
    }
};
