<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Drop + re-add rather than ->change() — this project doesn't have doctrine/dbal installed.
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('timezone_offset');
        });

        Schema::table('users', function (Blueprint $table) {
            // Decimal, not integer: quarter/half-hour timezones exist (e.g. India +5:30, Nepal +5:45).
            $table->decimal('timezone_offset', 4, 2)->default(0)->after('pomodoro_long_every');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('timezone_offset');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->integer('timezone_offset')->default(0)->after('pomodoro_long_every');
        });
    }
};
