<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A shared class/group agenda. The first genuinely multi-user object in
        // the app — everything else is scoped to a single owner.
        Schema::create('agenda_spaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();

            $table->string('name');
            // Short, hand-typeable invite code (see AgendaSpace::generateInviteCode).
            $table->string('invite_code', 12)->unique();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agenda_spaces');
    }
};
