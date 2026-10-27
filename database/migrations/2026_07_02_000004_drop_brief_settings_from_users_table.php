<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['brief_when', 'brief_time', 'brief_dismissed_on']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('brief_when', 10)->default('evening')->after('task_reset_time');
            $table->string('brief_time', 5)->default('19:00')->after('brief_when');
            $table->date('brief_dismissed_on')->nullable()->after('brief_time');
        });
    }
};
