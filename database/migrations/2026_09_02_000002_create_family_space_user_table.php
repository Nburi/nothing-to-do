<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Membership. A user can belong to more than one family space at once
        // (in-laws, a shared flat), so this is a pivot rather than a column on
        // users — same reasoning as agenda_space_user.
        //
        // `color` lives here, not on users: it's a per-membership attribute, not
        // a per-account one — someone in two family spaces can have a different
        // card color in each, and the small fixed palette (App\Livewire\Support\
        // FamilyColors) is deliberately unrelated to this app's own 4-tone
        // Topografie accent system (forest/contour/overprint/signal already each
        // mean something specific elsewhere — see CLAUDE.md). Not nullable: every
        // write path that attaches a member (FamilySpace::nextAvailableColor())
        // supplies one, so a member row with no color would only ever mean a bug.
        Schema::create('family_space_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_space_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('color', 20);

            $table->timestamps();

            // Joining twice must be a no-op, not a second row.
            $table->unique(['family_space_id', 'user_id'], 'family_space_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('family_space_user');
    }
};
