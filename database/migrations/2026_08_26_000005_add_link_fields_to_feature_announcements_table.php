<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lets an announcement link to an arbitrary external URL as an alternative
     * to related_module (an internal AppModules::CATALOG page) — the two are
     * mutually exclusive, enforced in App\Livewire\Admin\AnnouncementEditor,
     * not here. highlight_selector is a plain CSS selector, only meaningful
     * alongside related_module (an external site can't run our highlight
     * script), read client-side from a `?highlight=` query param appended to
     * the "ansehen" link — see resources/js/app.js.
     */
    public function up(): void
    {
        Schema::table('feature_announcements', function (Blueprint $table) {
            $table->string('external_url', 2048)->nullable()->after('related_module');
            $table->string('external_link_label')->nullable()->after('external_url');
            $table->string('highlight_selector')->nullable()->after('external_link_label');
        });
    }

    public function down(): void
    {
        Schema::table('feature_announcements', function (Blueprint $table) {
            $table->dropColumn(['external_url', 'external_link_label', 'highlight_selector']);
        });
    }
};
