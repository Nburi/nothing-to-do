<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per (user, module) recording that this user has ever opened
     * that module's page — see App\Http\Middleware\RecordModuleVisit, which
     * stamps it on every matching page load. `updated_at` doubles as "last
     * visited"; `created_at` is the first-ever visit. Powers
     * App\Models\FeatureAnnouncement's "only for people who actually use
     * this page" audience scoping — a real usage signal rather than a
     * settings toggle, since a module can be visible/enabled without ever
     * having been opened.
     */
    public function up(): void
    {
        Schema::create('module_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // A key into App\Models\FeatureAnnouncement::scopableModules() —
            // deliberately not a foreign key, same reasoning as
            // feature_announcements.related_module: the catalog is a
            // stateless PHP constant, not a database table.
            $table->string('module_key');

            $table->timestamps();

            $table->unique(['user_id', 'module_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_visits');
    }
};
