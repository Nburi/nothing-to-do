<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Refactor step 2.1: the theme is a per-user setting (REQUIREMENTS § 7, CONVENTIONS 9).
 *
 * Additive with defaults: every existing user gets Topografie, which is the look the old
 * app already has, and "system" light/dark, which is what the old app did (it followed
 * prefers-color-scheme). Nothing is read from or written to another column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('theme', 32)->default('topografie');
            $table->string('color_mode', 8)->default('system');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['theme', 'color_mode']);
        });
    }
};
