<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // A fourth, independent notification moment: a heads-up 5 minutes
            // before a schedule block starts, alongside (not instead of)
            // notify_event_start.
            $table->boolean('notify_event_upcoming')->default(false)->after('notify_break_start');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('notify_event_upcoming');
        });
    }
};
