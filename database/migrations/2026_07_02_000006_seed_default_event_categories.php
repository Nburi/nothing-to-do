<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<int, array{name:string,color:string,pomodoro_enabled:bool}> */
    private const DEFAULTS = [
        ['name' => 'Schule', 'color' => 'contour', 'pomodoro_enabled' => false],
        ['name' => 'Training', 'color' => 'forest', 'pomodoro_enabled' => false],
        ['name' => 'Arbeiten', 'color' => 'overprint', 'pomodoro_enabled' => true],
        ['name' => 'Abmachen', 'color' => 'signal', 'pomodoro_enabled' => false],
    ];

    /** Data migration: pre-fill every existing user with the 4 default categories. */
    public function up(): void
    {
        $now = now();
        $alreadySeeded = DB::table('event_categories')->distinct()->pluck('user_id');
        $userIds = DB::table('users')->whereNotIn('id', $alreadySeeded)->pluck('id');

        $rows = [];
        foreach ($userIds as $userId) {
            foreach (self::DEFAULTS as $sortOrder => $category) {
                $rows[] = [
                    'user_id' => $userId,
                    'name' => $category['name'],
                    'color' => $category['color'],
                    'pomodoro_enabled' => $category['pomodoro_enabled'],
                    'sort_order' => $sortOrder,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($rows !== []) {
            DB::table('event_categories')->insert($rows);
        }
    }

    /** Best-effort: remove rows matching the seeded defaults by name. */
    public function down(): void
    {
        DB::table('event_categories')->whereIn('name', array_column(self::DEFAULTS, 'name'))->delete();
    }
};
