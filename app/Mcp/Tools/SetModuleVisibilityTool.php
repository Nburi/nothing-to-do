<?php

namespace App\Mcp\Tools;

use App\Mcp\Exceptions\McpToolExecutionException;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Models\User;
use App\Services\AppModules;

/**
 * Mirrors ManagesModuleSettings::toggleModule() exactly (same self-healing
 * rule: hiding the user's current default landing page resets it to the
 * board in the same write) — that trait is Livewire-coupled ($this->
 * defaultPage local property), so this re-implements the same small,
 * well-tested logic against the model directly rather than pulling Livewire
 * into a non-HTTP-request tool context.
 */
class SetModuleVisibilityTool extends McpTool
{
    public function name(): string
    {
        return 'set_module_visibility';
    }

    public function description(): string
    {
        return 'Show or hide one of the app\'s optional feature modules (prepare, schedule, weekplan, '
            .'agenda, crafts, emergency, progress). The board and settings can never be hidden. Hiding '
            .'the user\'s current default landing page resets it back to the board automatically.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'module' => ['type' => 'string', 'enum' => array_keys(AppModules::CATALOG)],
                'hidden' => ['type' => 'boolean'],
            ],
            'required' => ['module', 'hidden'],
        ];
    }

    public function requiredAbility(): ?string
    {
        return McpAbility::WRITE;
    }

    public function annotations(): array
    {
        return ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true];
    }

    public function handle(User $user, array $arguments): array
    {
        $module = $arguments['module'] ?? null;
        $hidden = $arguments['hidden'] ?? null;

        if (! is_string($module) || ! array_key_exists($module, AppModules::CATALOG) || ! is_bool($hidden)) {
            throw new McpToolExecutionException('Not a valid module key, or "hidden" is missing. Valid modules: '.implode(', ', array_keys(AppModules::CATALOG)));
        }

        $currentlyHidden = AppModules::hiddenKeys($user);

        $newHidden = $hidden
            ? array_values(array_unique([...$currentlyHidden, $module]))
            : array_values(array_diff($currentlyHidden, [$module]));

        $updates = ['hidden_modules' => $newHidden];

        if ($hidden && $user->default_page === $module) {
            $updates['default_page'] = 'app';
        }

        $user->update($updates);

        return [
            'module' => $module,
            'hidden' => $hidden,
            'default_page' => $user->fresh()->default_page,
        ];
    }
}
