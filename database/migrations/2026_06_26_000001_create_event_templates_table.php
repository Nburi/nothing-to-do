<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            // A Topografie colour token: contour | forest | overprint | signal | ink.
            $table->string('color')->default('contour');
            // Default length in minutes when the template is dropped onto a day.
            $table->unsignedSmallInteger('duration')->default(60);
            // Optional preferred start time (HH:MM) — null = wherever it is dropped.
            $table->string('default_start', 5)->nullable();

            // Recurring templates auto-materialise an occurrence on matching days.
            $table->boolean('is_recurring')->default(false);
            // ISO weekday mask, e.g. "1,2,3,4,5" (Mon–Fri). Null = no recurrence.
            $table->string('recurrence')->nullable();

            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_templates');
    }
};
