<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_category_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // 'text' | 'number' | 'select' | 'checkbox'
            $table->string('type');
            // Only for 'select': [{"label": "...", "color": "..."}, ...]
            $table->json('options')->nullable();
            // Only for 'number': an optional short unit label, e.g. "Min".
            $table->string('unit')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_attributes');
    }
};
