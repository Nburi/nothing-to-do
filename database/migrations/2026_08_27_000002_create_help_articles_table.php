<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One Hilfe-Center page — admin-authored Markdown, read by every account
     * once published. Same draft/publish shape as FeatureAnnouncement:
     * published_at is stamped the first time and never moves again.
     */
    public function up(): void
    {
        Schema::create('help_articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->longText('content')->nullable();
            $table->foreignId('help_category_id')->nullable()->constrained('help_categories')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['help_category_id', 'is_published']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('help_articles');
    }
};
