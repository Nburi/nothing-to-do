<?php

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * GroupPage::reorder()/swipeIntent()/setToday() wrote `is_today` without ever
 * touching `today_date` (unlike every other write site — TaskBoard,
 * ProjectPage, PrepareTomorrow, ManagesTasks, the API controllers), so
 * toggling "today" on a grouped task silently corrupted ProgressStats' data
 * foundation in two directions. Fixed at the source in GroupPage itself;
 * this migration repairs rows already corrupted by it before that fix shipped.
 */
return new class extends Migration
{
    public function up(): void
    {
        // A task no longer flagged today, but still carrying a stale
        // today_date — marooned that day as permanently "imperfect" for the
        // streak, since Task::todayDateFor() always clears to null on exit.
        DB::table('tasks')
            ->where('is_today', false)
            ->whereNotNull('today_date')
            ->update(['today_date' => null]);

        // A task currently flagged today, but today_date was never stamped —
        // invisible to ProgressStats. Backfill with each user's own local
        // "today", the same value a correct write would have used since
        // these tasks are still actively flagged right now.
        User::query()->each(function (User $user) {
            Task::query()
                ->forUser($user)
                ->where('is_today', true)
                ->whereNull('today_date')
                ->update(['today_date' => $user->localToday()->toDateString()]);
        });
    }

    public function down(): void
    {
        // Data repair only, no schema change — and the corrupted values
        // (a stale leftover date, or a missing one) weren't worth keeping.
    }
};
