<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Default false, deliberately — an automatic planner is exactly the
            // kind of thing that can feel intrusive to someone who didn't ask for
            // it, so this feature opts in rather than lighting up for everyone.
            $table->boolean('planner_enabled')->default(false)->after('header_badges');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('planner_enabled');
        });
    }
};
