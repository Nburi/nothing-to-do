<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agenda_entries', function (Blueprint $table) {
            // Null = a private entry, exactly as every existing row already is.
            // nullOnDelete: deleting a space must not delete the homework its
            // members wrote — each entry simply falls back to being private to
            // whoever created it.
            $table->foreignId('agenda_space_id')
                ->nullable()
                ->after('user_id')
                ->constrained()
                ->nullOnDelete();

            $table->index(['agenda_space_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::table('agenda_entries', function (Blueprint $table) {
            $table->dropIndex(['agenda_space_id', 'date']);
            $table->dropConstrainedForeignId('agenda_space_id');
        });
    }
};
