<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Standalone, like agenda_entries — no FK to tasks/projects. Bastelideen are a
        // "someday, when bored" drawer, deliberately kept out of the board/Today/Notfall.
        Schema::create('craft_ideas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('title');
            $table->text('where_to_begin')->nullable();
            $table->boolean('is_done')->default(false);

            $table->timestamps();

            $table->index(['user_id', 'is_done']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('craft_ideas');
    }
};
