<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A shared household task list — several accounts, one list, distinct
        // from every other multi-user surface in this app (Agenda, Hilfe-Center):
        // membership + invite-code shape copied straight from AgendaSpace, since
        // that's the one place this exact problem was already solved correctly.
        Schema::create('family_spaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();

            $table->string('name');
            // Short, hand-typeable invite code (see FamilySpace::generateInviteCode).
            $table->string('invite_code', 12)->unique();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('family_spaces');
    }
};
