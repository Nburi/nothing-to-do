<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // null = nothing hidden (every module visible) — the same "untouched
            // means default" convention as header_badges, just inverted: an empty
            // hide-list needs no merge-with-catalog logic the way an enable-list
            // would, since "not in the list" already means "visible".
            $table->json('hidden_modules')->nullable()->after('header_badges');

            // Which route opens when the user lands on '/' or after login.
            // 'app' (the board) is always a safe value and never hideable — see
            // App\Services\AppModules.
            $table->string('default_page')->default('app')->after('hidden_modules');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['hidden_modules', 'default_page']);
        });
    }
};
