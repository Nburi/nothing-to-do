<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Weg-/Pufferzeit: how long you're travelling before an entry starts, and how
 * long you're still not available after it ends. Minutes, default 0 — an
 * entry without them behaves exactly as it always did.
 *
 * Both tables get the columns: a recurring block has to carry its travel time
 * with it, otherwise it would have to be re-entered on every occurrence,
 * which is precisely what the Wochenplan exists to avoid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_events', function (Blueprint $table) {
            $table->unsignedSmallInteger('buffer_before')->default(0)->after('end_time');
            $table->unsignedSmallInteger('buffer_after')->default(0)->after('buffer_before');
        });

        Schema::table('event_templates', function (Blueprint $table) {
            $table->unsignedSmallInteger('buffer_before')->default(0)->after('duration');
            $table->unsignedSmallInteger('buffer_after')->default(0)->after('buffer_before');
        });
    }

    public function down(): void
    {
        Schema::table('schedule_events', function (Blueprint $table) {
            $table->dropColumn(['buffer_before', 'buffer_after']);
        });

        Schema::table('event_templates', function (Blueprint $table) {
            $table->dropColumn(['buffer_before', 'buffer_after']);
        });
    }
};
