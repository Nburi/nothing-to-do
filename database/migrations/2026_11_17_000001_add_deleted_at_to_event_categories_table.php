<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step 4.1: deleting a category is undoable ("Rückgängig", CONVENTIONS 7), so it is a soft delete.
 * A hard delete would cascade away the category's custom attributes and their values on every
 * occurrence, and null out category_id on its blocks; none of that could be undone.
 *
 * Additive and nullable: every existing category stays as it is. down() only drops the column
 * (soft-deleted categories would then show again, nothing is lost).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_categories', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('event_categories', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
