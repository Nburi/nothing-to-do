<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Refactor step 3.4: the old Bastelideen (craft_ideas) become Later items with a "where to begin"
 * note (docs/REFACTOR_PLAN.md § 3). The copy is done by `php artisan app:copy-craft-ideas` at the
 * switch (step 5.4), never by a migration; this table only remembers which craft idea was copied
 * into which task, so a second run copies nothing twice.
 *
 * craft_ideas itself is never changed or deleted. No foreign key to tasks on purpose: when a user
 * deletes the copied task, the link stays and a re-run does not bring the idea back.
 * Additive; down() drops only this table (the copied tasks stay, they are the user's tasks now).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('craft_idea_copies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('craft_idea_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('task_id')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('craft_idea_copies');
    }
};
