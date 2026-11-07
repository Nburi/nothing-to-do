<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Refactor step 1.6: areas (REQUIREMENTS § 4). An open, user-defined list of
 * task areas (School, Sport, Own projects), kept separate from the week-plan
 * categories but linked to them: each category chooses which areas may be
 * suggested inside its blocks.
 *
 * Purely additive. Existing event categories keep every column, including
 * `pomodoro_enabled`, which already is the "pomodoro sessions" switch — no
 * data needs moving. A category without links accepts only tasks without an
 * area (engine decision E5), so existing users see no change until they
 * create areas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->string('color', 20)->default('ink');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'sort_order']);
        });

        Schema::create('category_area', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('area_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            // Explicit short name: the default would be 52 characters, under
            // MariaDB's 64 limit, but this keeps it safe if a column is renamed.
            $table->unique(['event_category_id', 'area_id'], 'category_area_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_area');
        Schema::dropIfExists('areas');
    }
};
