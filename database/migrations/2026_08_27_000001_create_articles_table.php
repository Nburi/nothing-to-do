<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Admin-authored long-form content — Blog/Doc/Leitfaden — read by every
     * account (see CLAUDE.md, "Bibliothek — Blog, Docs & Leitfäden"). Same
     * draft/publish shape as feature_announcements: is_published toggles
     * visibility, published_at is stamped the first time and never moves
     * again on a later unpublish/republish.
     */
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('type')->default('doc');
            $table->longText('content')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'is_published']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
