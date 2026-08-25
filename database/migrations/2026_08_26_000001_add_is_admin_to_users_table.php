<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Set directly in the DB (or via `php artisan admin:grant {email}`, see
     * app/Console/Commands/GrantAdmin.php) — no in-app self-service "become
     * admin" flow exists on purpose. Gates App\Livewire\Admin\AnnouncementEditor.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('email_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });
    }
};
