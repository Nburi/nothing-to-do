<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Membership. A user can belong to several spaces at once (a class plus
        // a study group), so this is a pivot rather than a column on users.
        Schema::create('agenda_space_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agenda_space_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->timestamps();

            // Joining twice must be a no-op, not a second row.
            $table->unique(['agenda_space_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agenda_space_user');
    }
};
