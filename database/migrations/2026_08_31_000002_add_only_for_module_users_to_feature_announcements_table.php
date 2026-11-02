<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lets an announcement tied to a module (related_module) be restricted
     * to people who have actually visited that module's page (see
     * module_visits / App\Http\Middleware\RecordModuleVisit), instead of
     * reaching everyone. Deliberately a separate, default-false flag rather
     * than an automatic consequence of setting related_module — an
     * announcement about a brand-new opt-in page (e.g. "Planer is here now!")
     * still needs to reach everyone, including people who have never opened
     * it yet, which is exactly the case a default-true behavior would break.
     */
    public function up(): void
    {
        Schema::table('feature_announcements', function (Blueprint $table) {
            $table->boolean('only_for_module_users')->default(false)->after('related_module');
        });
    }

    public function down(): void
    {
        Schema::table('feature_announcements', function (Blueprint $table) {
            $table->dropColumn('only_for_module_users');
        });
    }
};
