# Errors that may come back

Errors and surprises worth remembering, with the fix. Newest first.

## Livewire tests no longer throw `ModelNotFoundException` (2026-11-06)

**Symptom.** After updating Livewire from 4.3.1 to 4.4.8, every ownership test that did
`$this->expectException(ModelNotFoundException::class)` before a `Livewire::test(...)->call(...)`
failed with "Failed asserting that exception ... is thrown" (19 tests).

**Cause.** Livewire 4.4 handles `ModelNotFoundException`, `AuthorizationException` and
`HttpException` inside component calls in tests the same way as in a real request: they become an
HTTP error response instead of being rethrown. The guards in the components still work; the data of
the other user stays untouched.

**Fix.** Assert the response instead of the exception:

```php
Livewire::test(TaskBoard::class)
    ->call('startFocusTimer', $foreignEvent->id)
    ->assertStatus(404);
```

Where it matters, also assert that the foreign row is unchanged (`assertDatabaseHas`).

## MCP OAuth tests fail with `LogicException: Invalid key supplied` (2026-11-16)

**Symptom.** In a fresh checkout, worktree or on CI, every `McpOAuth*` test gets a 500 instead of 200/401.

**Cause.** Passport signs the MCP OAuth tokens with `storage/oauth-private.key` and `storage/oauth-public.key`. Both are gitignored, so a new checkout has none.

**Fix.** Run `php artisan passport:keys --no-interaction` once per checkout. CI does it in the "Prepare environment" step of `.github/workflows/tests.yml`.
