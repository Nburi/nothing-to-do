<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_event_attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_attribute_id')->constrained()->cascadeOnDelete();
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['schedule_event_id', 'category_attribute_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_event_attribute_values');
    }
};
