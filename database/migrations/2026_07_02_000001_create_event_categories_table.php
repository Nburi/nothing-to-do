<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            // A Topografie colour token: contour | forest | overprint | signal | ink.
            $table->string('color')->default('contour');
            // Whether this category drives the header's Pomodoro focus timer.
            $table->boolean('pomodoro_enabled')->default(false);

            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_categories');
    }
};
