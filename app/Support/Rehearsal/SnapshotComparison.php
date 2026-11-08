<?php

namespace App\Support\Rehearsal;

/**
 * Compares two DatabaseSnapshots. A problem is anything that means data may
 * be lost or mangled: fewer rows, a row that is gone, a changed value in a
 * column that existed before, a dropped table or column, or a copied column
 * that does not match its source. New tables, columns and rows are notes.
 */
final readonly class SnapshotComparison
{
    /**
     * @param  list<string>  $problems
     * @param  list<string>  $notes
     */
    private function __construct(
        public array $problems,
        public array $notes,
    ) {}

    public function ok(): bool
    {
        return $this->problems === [];
    }

    /**
     * @param  array<string, string>  $same  "table.old_column" => "table.new_column": the new column must hold the old one's values
     * @param  list<string>  $allowDropped  "table.column" or "table" that may be gone (the drop step of a two-step rename)
     * @param  list<string>  $ignore  "table.column" whose changes are expected
     */
    public static function compare(array $before, array $after, array $same = [], array $allowDropped = [], array $ignore = []): self
    {
        $problems = [];
        $notes = [];

        foreach ($before['tables'] as $table => $old) {
            $new = $after['tables'][$table] ?? null;

            if ($new === null) {
                if (! in_array($table, $allowDropped, true)) {
                    $problems[] = "{$table}: table dropped ({$old['rows']} rows)";
                }

                continue;
            }

            if ($new['rows'] < $old['rows']) {
                $problems[] = "{$table}: {$old['rows']} rows before, {$new['rows']} after";
            } elseif ($new['rows'] > $old['rows']) {
                $notes[] = "{$table}: ".($new['rows'] - $old['rows']).' new rows';
            }

            $common = array_values(array_intersect($old['columns'], $new['columns']));
            foreach (array_diff($old['columns'], $new['columns']) as $column) {
                if (! in_array("{$table}.{$column}", $allowDropped, true)) {
                    $problems[] = "{$table}.{$column}: column dropped";
                }
            }
            foreach (array_diff($new['columns'], $old['columns']) as $column) {
                $notes[] = "{$table}.{$column}: new column";
            }

            if ($old['key'] === 'id' && $new['key'] === 'id') {
                $gone = array_diff_key($old['values']['id'], $new['values']['id']);
                if ($gone !== []) {
                    $problems[] = "{$table}: ".count($gone).' rows from before are gone (ids '.self::ids(array_keys($gone)).')';
                }

                foreach ($common as $column) {
                    if ($column === 'id' || in_array("{$table}.{$column}", $ignore, true)) {
                        continue;
                    }
                    $changed = self::changedIds($old['values'][$column], $new['values'][$column]);
                    if ($changed !== []) {
                        $problems[] = "{$table}.{$column}: values changed";
                        $notes[] = "{$table}.{$column}: changed in ids ".self::ids($changed);
                    }
                }
            } else {
                $missing = self::missingRows($old['row_hashes'] ?? [], $new['row_hashes'] ?? [], $common);
                if ($missing > 0) {
                    $problems[] = "{$table}: {$missing} rows changed or gone";
                }
            }
        }

        foreach ($after['tables'] as $table => $new) {
            if (! isset($before['tables'][$table])) {
                $notes[] = "{$table}: new table ({$new['rows']} rows)";
            }
        }

        foreach ($same as $source => $target) {
            [$sourceTable, $sourceColumn] = explode('.', $source, 2);
            [$targetTable, $targetColumn] = explode('.', $target, 2);
            $old = $before['tables'][$sourceTable]['values'][$sourceColumn] ?? null;
            $new = $after['tables'][$targetTable]['values'][$targetColumn] ?? null;

            if ($old === null || $new === null) {
                $problems[] = "{$target} or {$source} not found for the copy check";

                continue;
            }
            if (self::changedIds($old, $new) !== [] || array_diff_key($old, $new) !== []) {
                $problems[] = "{$target} does not match {$source} from before";
            }
        }

        return new self($problems, $notes);
    }

    /**
     * @param  array<string, string>  $old
     * @param  array<string, string>  $new
     * @return list<string>
     */
    private static function changedIds(array $old, array $new): array
    {
        $changed = [];
        foreach ($old as $id => $hash) {
            if (isset($new[$id]) && $new[$id] !== $hash) {
                $changed[] = (string) $id;
            }
        }

        return $changed;
    }

    /** Rows of $old (restricted to $columns) that no longer exist in $new, as a multiset. */
    private static function missingRows(array $old, array $new, array $columns): int
    {
        $key = fn (array $row) => implode('|', array_map(fn ($c) => $row[$c] ?? '-', $columns));
        $available = array_count_values(array_map($key, $new));
        $missing = 0;
        foreach (array_map($key, $old) as $k) {
            if (($available[$k] ?? 0) > 0) {
                $available[$k]--;
            } else {
                $missing++;
            }
        }

        return $missing;
    }

    /** @param  list<string|int>  $ids */
    private static function ids(array $ids): string
    {
        $shown = array_slice($ids, 0, 10);

        return implode(', ', $shown).(count($ids) > 10 ? ', …' : '');
    }
}
