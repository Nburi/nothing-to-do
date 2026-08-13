<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Superseded by the group_notes table (see the sibling migration) before
     * this feature ever shipped — no deployed data depends on this column, so
     * it is dropped directly rather than staged across two releases.
     */
    public function up(): void
    {
        Schema::table('task_groups', function (Blueprint $table) {
            $table->dropColumn('notes');
        });
    }

    public function down(): void
    {
        Schema::table('task_groups', function (Blueprint $table) {
            $table->longText('notes')->nullable();
        });
    }
};
