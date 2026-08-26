<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * The one supported way to make someone an admin — there is deliberately no
 * in-app self-service flow (see CLAUDE.md, Feature-Ankündigungen). Exists
 * because `php artisan tinker --execute` mangles quotes when called from
 * PowerShell (see CLAUDE.md §10), so a raw tinker one-liner isn't a reliable
 * way to flip this column locally either.
 */
class GrantAdmin extends Command
{
    protected $signature = 'admin:grant {email} {--revoke : Remove admin instead of granting it}';

    protected $description = 'Grant (or, with --revoke, remove) admin rights for the user with the given email';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->error("No user found with email {$this->argument('email')}.");

            return self::FAILURE;
        }

        $grant = ! $this->option('revoke');
        $user->update(['is_admin' => $grant]);

        $this->info($grant
            ? "{$user->email} is now an admin."
            : "{$user->email} is no longer an admin.");

        return self::SUCCESS;
    }
}
