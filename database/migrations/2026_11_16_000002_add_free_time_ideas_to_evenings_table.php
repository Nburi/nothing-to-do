<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Refactor step 3.4: free-time ideas from Later (R6, risk F9). Tonight's evening row remembers
 * what was offered, so a reload shows the same idea and no idea comes twice the same day.
 *
 * - idea_task_id / idea_slot_until: the idea offered in the free slot that ends at idea_slot_until
 *   (a Later task; no foreign key, a deleted or finished task simply is not offered any more).
 *   A slot row with idea_task_id null means "Later had nothing new left for this slot".
 * - idea_shown_ids: every idea offered today (json list of task ids), the "not twice a day" rule.
 * - mine_from / mine_until: the free slot the user kept "Meins behalten"; no idea there.
 *
 * Additive and nullable: every existing evening keeps working unchanged; down() drops the columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evenings', function (Blueprint $table) {
            $table->unsignedBigInteger('idea_task_id')->nullable();
            $table->timestamp('idea_slot_until')->nullable();
            $table->json('idea_shown_ids')->nullable();
            $table->timestamp('mine_from')->nullable();
            $table->timestamp('mine_until')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('evenings', function (Blueprint $table) {
            $table->dropColumn(['idea_task_id', 'idea_slot_until', 'idea_shown_ids', 'mine_from', 'mine_until']);
        });
    }
};
