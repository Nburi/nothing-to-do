# Command-Palette (Strg/⌘+K)

One search box that jumps anywhere. Opens with **Strg/⌘+K** or a bare **/** (same typing guards as `N`),
the search icon in the header (from `sm` up), or "Suchen & springen" at the top of the avatar menu on phones.

- `App\Services\PaletteSearch` — stateless read side. Pages (module-aware: a hidden module and the Planer
  toggle both remove their page; admin pages only for admins; Notfallmodus stays while an emergency runs),
  plus the user's active tasks, projects, groups, open agenda entries, open Bastelideen and published help
  articles. Every read uses the model's own ownership scope; max 5 per group. An empty query shows pages only.
- LIKE wildcards typed by the user are matched literally. The escape character is `!` with an explicit
  `ESCAPE '!'` clause — SQLite has no default escape character and MySQL treats backslash specially.
- `App\Livewire\CommandPalette` — thin wrapper, mounted once in `layouts/app.blade.php` next to QuickCapture.
  Only the query round-trips (`wire:model.live.debounce.120ms`).
- Open/closed and the keyboard cursor live in the Alpine store `commandPalette` (`resources/js/app.js`).
  The cursor is whichever `[data-palette-item]` carries `aria-selected` — never mirrored into Alpine state,
  because Livewire re-renders the list underneath it. The result list is keyed on the query so a new result
  set replaces nodes instead of morphing them (no stale `aria-selected`). `updatedQuery()` dispatches
  `command-palette-results` so the cursor jumps back to the first row.
- A task result opens the board with `?task=<id>` (existing deep link → edit sheet); a project task links to
  its project page, a grouped task to its group page (they have no board card).
- **"„…“ erfassen"** row (whenever something is typed): closes the palette and opens QuickCapture with the
  sentence pre-filled (`QuickCapture::resetPanel(?string $title)`), so "I searched and it isn't there" is one
  keystroke from "now it is".
- Not built (ideas): recent items, verbs ("Heute: …", "Erledige: …"), fuzzy matching, searching notes/
  brainstorm text, searching completed tasks.

Verification: `tests/Feature/CommandPaletteTest.php`. No live browser pass was possible when this was built
(Chrome extension unresponsive) — an eyeball check of the overlay and the keyboard cursor is still owed.
