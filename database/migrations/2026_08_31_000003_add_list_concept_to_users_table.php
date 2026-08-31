<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Which mental model the board renders through — 'three_things' matches
            // current behavior exactly, so every existing account keeps seeing
            // exactly what it sees today until it explicitly picks something else.
            // See App\Services\ListConcepts.
            $table->string('list_concept')->default('three_things')->after('default_page');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('list_concept');
        });
    }
};
