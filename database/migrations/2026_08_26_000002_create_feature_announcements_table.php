<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Admin-authored "here's what's new" entries. A row can sit as a draft
     * (is_published false) while an admin writes it, then go live — published_at
     * is stamped the first time it's published and never moves again on a later
     * unpublish/republish, since it marks when the announcement was actually
     * introduced, not the current toggle state.
     */
    public function up(): void
    {
        Schema::create('feature_announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');

            // A key into App\Services\AppModules::CATALOG, or null for "no
            // specific page". Deliberately not a foreign key — the catalog is a
            // stateless PHP constant, not a database table.
            $table->string('related_module')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_announcements');
    }
};
