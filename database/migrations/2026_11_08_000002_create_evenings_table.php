<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Refactor step 1.3: one row per user and local date for the evening loop.
 * Holds the battery check-in (G4: asked once, skippable, changeable) and the
 * tasks the user skipped with "Another" tonight, which doubles as the swap log
 * risk F3 asks for. Step 3.2 adds the reset to it.
 *
 * New table, nothing to migrate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evenings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('battery', 8)->nullable();
            $table->json('skipped_task_ids')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evenings');
    }
};
