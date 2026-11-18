<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\NarrowAbility;
use Illuminate\Console\Command;

/**
 * Refactor step 1.5: the narrow token for the capture Shortcut (R3, risk C10).
 * It can only call POST /api/capture: no reading, no other writes. Shown
 * once, stored hashed, revocable in Settings like every Sanctum token. A
 * settings screen for it comes with the capture UI (2.3).
 */
class CreateCaptureToken extends Command
{
    protected $signature = 'app:capture-token {email}';

    protected $description = 'Create a token that can only add things to the Inbox (iPhone Shortcut, refactor step 1.5)';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->error("Kein Konto mit der E-Mail {$this->argument('email')}.");

            return self::FAILURE;
        }

        $token = $user->createToken('iPhone: In die Inbox', [NarrowAbility::CAPTURE])->plainTextToken;

        $this->info('Token für den Kurzbefehl (wird nur jetzt angezeigt):');
        $this->line($token);
        $this->newLine();
        $this->line('Es kann nur POST /api/capture aufrufen, mit {"text": "…"}. Widerrufen: in der App unter Einstellungen › Shortcuts, API & MCP.');

        return self::SUCCESS;
    }
}
