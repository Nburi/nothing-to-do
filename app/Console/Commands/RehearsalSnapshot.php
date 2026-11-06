<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Rehearsal\DatabaseSnapshot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Refactor spike 0.3: fingerprint the current database before or after a
 * migration rehearsal. Read-only. Written to storage/app/rehearsal/{name}.json
 * (git-ignored); holds counts and hashes, never the data itself.
 */
class RehearsalSnapshot extends Command
{
    protected $signature = 'app:rehearsal-snapshot {name : e.g. before, after, rolled-back} {--user= : email of the account to spot-check}';

    protected $description = 'Fingerprint the database (row counts and value hashes) for a migration rehearsal';

    public function handle(DatabaseSnapshot $snapshots): int
    {
        $user = null;
        if ($email = $this->option('user')) {
            $user = User::where('email', $email)->first();
            if ($user === null) {
                $this->error("No account with the email {$email}.");

                return self::FAILURE;
            }
        }

        $this->line('Database: <comment>'.DB::connection()->getDatabaseName().'</comment> ('.DB::connection()->getDriverName().')');

        $snapshot = $snapshots->take($user);
        $path = storage_path('app/rehearsal/'.$this->argument('name').'.json');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($snapshot, JSON_UNESCAPED_SLASHES));

        $rows = array_sum(array_column($snapshot['tables'], 'rows'));
        $this->info(count($snapshot['tables'])." tables, {$rows} rows → {$path}");
        if ($user !== null) {
            $this->table(['Table', "Rows of {$email}"], collect($snapshot['user']['tables'])->map(fn ($n, $t) => [$t, $n])->values());
        }

        return self::SUCCESS;
    }
}
