<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\NarrowAbility;
use Illuminate\Console\Command;

/**
 * Spike 0.1: create the narrow token for the "home Wi-Fi" iPhone Shortcut.
 * A command instead of a settings screen on purpose: the spike decides first
 * whether the trigger works at all (see docs/spikes/0.1-arrival.md). The
 * token is shown once and only stored hashed, like every Sanctum token.
 */
class CreateArrivalToken extends Command
{
    protected $signature = 'app:arrival-token {email}';

    protected $description = 'Create a token that can only report "arrived home" (iPhone Shortcut, refactor spike 0.1)';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->error("Kein Konto mit der E-Mail {$this->argument('email')}.");

            return self::FAILURE;
        }

        $token = $user->createToken('iPhone: Ankunft zuhause', [NarrowAbility::ARRIVAL])->plainTextToken;

        $this->info('Token für den Kurzbefehl (wird nur jetzt angezeigt):');
        $this->line($token);
        $this->newLine();
        $this->line('Es kann nur POST /api/arrival aufrufen. Widerrufen: in der App unter Einstellungen › Shortcuts, API & MCP.');

        return self::SUCCESS;
    }
}
