<?php

namespace App\Support\Rehearsal;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A fingerprint of the whole database for migration rehearsals (refactor
 * spike 0.3, R8, risk C6): row counts per table and, per row and column, a
 * short hash of the value. Never the values themselves, so a snapshot of a
 * production copy holds no readable user data.
 *
 * Rows are keyed by `id` where a table has one, so the comparison can tell
 * "row 17 is gone" or "row 17's deadline changed" apart from "rows were
 * added". Tables without an id (pivots) are compared as a multiset of rows.
 */
final class DatabaseSnapshot
{
    /** Tables whose content changes on its own and says nothing about migrations. */
    public const VOLATILE_TABLES = ['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'];

    /** @return array<string, mixed> */
    public function take(?User $spotCheck = null): array
    {
        $tables = [];
        foreach ($this->tableNames() as $table) {
            if (in_array($table, self::VOLATILE_TABLES, true)) {
                continue;
            }
            $tables[$table] = $this->fingerprint($table);
        }

        $snapshot = [
            'version' => 1,
            'taken_at' => now()->toIso8601String(),
            'connection' => DB::getDefaultConnection(),
            'driver' => DB::connection()->getDriverName(),
            'last_migration' => Schema::hasTable('migrations') ? DB::table('migrations')->orderByDesc('id')->value('migration') : null,
            'tables' => $tables,
        ];

        if ($spotCheck !== null) {
            $snapshot['user'] = ['id' => $spotCheck->id, 'tables' => $this->userCounts($spotCheck)];
        }

        return $snapshot;
    }

    /** @return list<string> */
    private function tableNames(): array
    {
        $names = array_map(fn (array $t) => $t['name'], Schema::getTables());
        sort($names);

        return $names;
    }

    /** @return array{rows:int,key:?string,columns:list<string>,values?:array<string,array<string,string>>,row_hashes?:list<array<string,string>>} */
    private function fingerprint(string $table): array
    {
        $columns = Schema::getColumnListing($table);
        sort($columns);
        $keyed = in_array('id', $columns, true);

        $result = ['rows' => 0, 'key' => $keyed ? 'id' : null, 'columns' => $columns];
        $values = array_fill_keys($columns, []);
        $rowHashes = [];

        $query = DB::table($table);
        $keyed ? $query->orderBy('id') : $query->orderBy($columns[0]);

        foreach ($query->cursor() as $row) {
            $row = (array) $row;
            $result['rows']++;
            $hashes = [];
            foreach ($columns as $column) {
                $hashes[$column] = self::hashValue($row[$column] ?? null);
            }
            if ($keyed) {
                foreach ($hashes as $column => $hash) {
                    $values[$column][(string) $row['id']] = $hash;
                }
            } else {
                $rowHashes[] = $hashes;
            }
        }

        if ($keyed) {
            $result['values'] = $values;
        } else {
            $result['row_hashes'] = $rowHashes;
        }

        return $result;
    }

    /** @return array<string, int> rows per table that belong to the user (tables with a user_id column) */
    private function userCounts(User $user): array
    {
        $counts = [];
        foreach ($this->tableNames() as $table) {
            if (Schema::hasColumn($table, 'user_id')) {
                $counts[$table] = DB::table($table)->where('user_id', $user->id)->count();
            }
        }
        $counts['users'] = DB::table('users')->where('id', $user->id)->count();

        return $counts;
    }

    /** Short, type-normalised hash: 1 and "1" are equal, null is not "". */
    public static function hashValue(mixed $value): string
    {
        return $value === null ? '-' : hash('xxh64', (string) $value);
    }
}
