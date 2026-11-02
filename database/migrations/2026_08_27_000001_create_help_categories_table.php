<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A folder in the Hilfe-Center sidebar. Self-referencing parent_id gives
     * one level of subfolders (folder → subfolder) without a rigid schema —
     * nothing stops deeper nesting later, but the sidebar UI only ever
     * renders two levels for now (see CLAUDE.md, "Hilfe-Center & Support").
     */
    public function up(): void
    {
        Schema::create('help_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('parent_id')->nullable()->constrained('help_categories')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('help_categories');
    }
};
